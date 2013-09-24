<?php 
require_once 'RBTree.php';
require_once 'Cell.php';
require_once 'Beachsection.php';
require_once 'CircleEvent.php';
require_once 'Edge.php';
require_once 'Halfedge.php';
require_once 'CircleEvent.php';

/**
 * Classe permettant de créer et de manipuler un diagramme de Voronoï, ou autrement
 * appelé polygones de Thyssen.
 * 
 * L'algorithme mis en place est celui de Steven Fortune, qui permet de calculer
 * les polygones en O(n*log(n))
 * 
 * Inspiré de la librairie JavaScript créée par gorhill
 * @link https://github.com/gorhill/Javascript-Voronoi
 * 
 * Sous license MIT.
 * 
 * @author Samuel ROZE <sroze@lillemetropole.fr>
 */
class Voronoi
{
	const EPSILON = 1e-9;
	const INFINITY = 1e30;
	
	/**
	 * Liste des bordures.
	 * 
	 * @var array
	 */
	protected $_edges = array();
	
	/**
	 * Liste des cellules.
	 * 
	 * @var array
	 */
	protected $_cells = array();
	
	private $_beachsectionJunkyard = array();
	private $_circleEventJunkyard = array();
	
	/**
	 * 
	 */
	protected $_beachline;
	
	protected $_circleEvents;
	private $_firstCircleEvent;
	
	/**
	 * Constructeur.
	 * 
	 */
	public function __construct ()
	{
		$this->_beachline = new RBTree();
		$this->_circleEvents = new RBTree();
		$this->_firstCircleEvent = null;
	}
	
	public function compute ($sites, $bbox) 
	{
		$starttime = microtime(true);
		
		// Initialize site event queue
		$siteEvents = array_slice($sites, 0);
		usort($siteEvents, function($a,$b){
			$r = $b->y - $a->y;
			if ($r) {return $r;}
			return $b->x - $a->x;
		});
	
		// process queue
		$site = array_pop($siteEvents);
		$siteid = 0;
		$xsitex = -self::INFINITY; // to avoid duplicate sites
		$xsitey = -self::INFINITY;
	
		// main loop
		for (;;) {
			// we need to figure whether we handle a site or circle event
			// for this we find out if there is a site event and it is
			// 'earlier' than the circle event
			$circle = $this->_firstCircleEvent;
	
			// add beach section
			if ($site && (!$circle || $site->y < $circle->y || ($site->y === $circle->y && $site->x < $circle->x))) {
				// only if site is not a duplicate
				if ($site->x !== $xsitex || $site->y !== $xsitey) {
					// first create cell for new site
					$this->_cells[$siteid] = new Cell($site);
					$site->id = $siteid++;
					
					// then create a beachsection for that site
					$this->addBeachsection($site);
					
					// remember last site coords to detect duplicate
					$xsitey = $site->y;
					$xsitex = $site->x;
				}
				
				$site = array_pop($siteEvents);
			}
	
			// remove beach section
			else if ($circle) {
				$this->removeBeachsection($circle->arc);
			}
	
			// all done, quit
			else {
				break;
			}
		}
	
		// wrapping-up:
		//   connect dangling edges to bounding box
		//   cut edges as per bounding box
		//   discard edges completely outside bounding box
		//   discard edges which are point-like
		$this->clipEdges($bbox);
	
		//   add missing edges in order to close opened cells
		$this->closeCells($bbox);
	
		// prepare return values
		$result = array(
			'sites' => $sites,
			'cells' => $this->_cells,
			'edges' => $this->_edges,
			'execTime' => (microtime(true) - $starttime) / 1000 // en secondes
		);
	
		return $result;
	}
	
	public function createEdge ($lSite, $rSite, $va, $vb) 
	{
		$edge = new Nurbs_Edge($lSite, $rSite);
		$this->_edges[] = $edge;
		
		if ($va) {
			$edge->setStartPoint($lSite, $rSite, $va);
		}
		if ($vb) {
			$edge->setEndPoint($lSite, $rSite, $vb);
		}
		
		$this->_cells[$lSite->id]->_halfedges[] = new Halfedge($edge, $lSite, $rSite);
		$this->_cells[$rSite->id]->_halfedges[] = new Halfedge($edge, $rSite, $lSite);

		return $edge;
	}

	public function createBorderEdge ($lSite, $va, $vb) 
	{
		$edge = new Nurbs_Edge($lSite, null);
		$edge->va = $va;
		$edge->vb = $vb;
		$this->_edges[] = $edge;
		
		return $edge;
	}
	
	// rhill 2011-06-02: A lot of Beachsection instanciations
	// occur during the computation of the Voronoi diagram;
	// somewhere between the number of sites and twice the
	// number of sites, while the number of Beachsections on the
	// beachline at any given time is comparatively low. For this
	// reason, we reuse already created Beachsections, in order
	// to avoid new memory allocation. This resulted in a measurable
	// performance gain.
	public function createBeachsection ($site) 
	{
		$beachsection = array_pop($this->_beachsectionJunkyard);
		if ($beachsection) {
			$beachsection->site = $site;
		} else {
			$beachsection = new Beachsection($site);
		}
		
		return $beachsection;
	}
	
	// calculate the left break point of a particular beach section;
	// given a particular sweep line
	public function leftBreakPoint ($arc, $directrix) 
	{
		// http://en.wikipedia.org/wiki/Parabola
		// http://en.wikipedia.org/wiki/Quadratic_equation
		// h1 = x1;
		// k1 = (y1+directrix)/2;
		// h2 = x2;
		// k2 = (y2+directrix)/2;
		// p1 = k1-directrix;
		// a1 = 1/(4*p1);
		// b1 = -h1/(2*p1);
		// c1 = h1*h1/(4*p1)+k1;
		// p2 = k2-directrix;
		// a2 = 1/(4*p2),rbInsertSuccessor
		// b2 = -h2/(2*p2);
		// c2 = h2*h2/(4*p2)+k2;
		// x = (-(b2-b1) + Math.sqrt((b2-b1)*(b2-b1) - 4*(a2-a1)*(c2-c1))) / (2*(a2-a1))
		// When x1 become the x-origin:
		// h1 = 0;
		// k1 = (y1+directrix)/2;
		// h2 = x2-x1;
		// k2 = (y2+directrix)/2;
		// p1 = k1-directrix;
		// a1 = 1/(4*p1);
		// b1 = 0;
		// c1 = k1;
		// p2 = k2-directrix;
		// a2 = 1/(4*p2);
		// b2 = -h2/(2*p2);
		// c2 = h2*h2/(4*p2)+k2;
		// x = (-b2 + Math.sqrt(b2*b2 - 4*(a2-a1)*(c2-k1))) / (2*(a2-a1)) + x1
	
		// change code below at your own risk: care has been taken to
		// reduce errors due to computers' finite arithmetic precision.
		// Maybe can still be improved, will see if any more of this
		// kind of errors pop up again.
		$site = $arc->site;
		$rfocx = $site->x;
		$rfocy = $site->y;
		$pby2 = $rfocy-$directrix;
		
		// parabola in degenerate case where focus is on directrix
		if (!$pby2) {
			return $rfocx;
		}
		
		$lArc = $arc->previous;
		if (!$lArc) {
			return -self::INFINITY;
		}
		
		$site = $lArc->site;
		$lfocx = $site->x;
		$lfocy = $site->y;
		$plby2 = $lfocy-$directrix;
		
		// parabola in degenerate case where focus is on directrix
		if (!$plby2) {
			return $lfocx;
		}
		
		$hl = $lfocx-$rfocx;
		$aby2 = 1/$pby2-1/$plby2;
		$b = $hl/$plby2;
		
		if ($aby2) {
			return (-$b+sqrt($b*$b-2*$aby2*($hl*$hl/(-2*$plby2)-$lfocy+$plby2/2+$rfocy-$pby2/2)))/$aby2+$rfocx;
		}
		
		// both parabolas have same distance to directrix, thus break point is midway
		return ($rfocx+$lfocx)/2;
	}
	
	// calculate the right break point of a particular beach section;
	// given a particular directrix
	public function rightBreakPoint ($arc, $directrix) 
	{
		$rArc = $arc->next;
		if ($rArc) {
			return $this->leftBreakPoint($rArc, $directrix);
		}
		
		$site = $arc->site;
		return $site->y === $directrix ? $site->x : self::INFINITY;
	}
	
	public function detachBeachsection ($beachsection) 
	{
		$this->detachCircleEvent($beachsection); // detach potentially attached circle event
		$this->_beachline->removeNode($beachsection); // remove from RB-tree
		$this->_beachsectionJunkyard[] = $beachsection; // mark for reuse
	}
	
	public function removeBeachsection ($beachsection) 
	{
		$circle = $beachsection->circleEvent;
		$x = $circle->x;
		$y = $circle->ycenter;
		$vertex = new Nurbs_Point($x, $y);
		$previous = $beachsection->previous;
		$next = $beachsection->next;
		$disappearingTransitions = array($beachsection);
		$abs_fn = 'abs';
	
		// remove collapsed beachsection from beachline
		$this->detachBeachsection($beachsection);
	
		// there could be more than one empty arc at the deletion point, this
		// happens when more than two edges are linked by the same vertex;
		// so we will collect all those edges by looking up both sides of
		// the deletion point.
		// by the way, there is *always* a predecessor/successor to any collapsed
		// beach section, it's just impossible to have a collapsing first/last
		// beach sections on the beachline, since they obviously are unconstrained
		// on their left/right side.
	
		// look left
		$lArc = $previous;
		while ($lArc->circleEvent && abs($x-$lArc->circleEvent->x)<self::EPSILON && abs($y-$lArc->circleEvent->ycenter)<self::EPSILON) {
			$previous = $lArc->previous;
			array_unshift($disappearingTransitions, $lArc);	//disappearingTransitions.unshift(lArc);
			$this->detachBeachsection($lArc); // mark for reuse
			$lArc = $previous;
		}
		
		// even though it is not disappearing, I will also add the beach section
		// immediately to the left of the left-most collapsed beach section, for
		// convenience, since we need to refer to it later as this beach section
		// is the 'left' site of an edge for which a start point is set.
		array_unshift($disappearingTransitions, $lArc);	//disappearingTransitions.unshift(lArc);
		$this->detachCircleEvent($lArc);
	
		// look right
		$rArc = $next;
		while ($rArc->circleEvent && abs($x-$rArc->circleEvent->x)<self::EPSILON && abs($y-$rArc->circleEvent->ycenter)<self::EPSILON) {
			$next = $rArc->next;
			$disappearingTransitions[] = $rArc;
			$this->detachBeachsection($rArc); // mark for reuse
			$rArc = $next;
		}
		
		// we also have to add the beach section immediately to the right of the
		// right-most collapsed beach section, since there is also a disappearing
		// transition representing an edge's start point on its left.
		$disappearingTransitions[] = $rArc;
		$this->detachCircleEvent($rArc);
	
		// walk through all the disappearing transitions between beach sections and
		// set the start point of their (implied) edge.
		$nArcs = count($disappearingTransitions);
		
		for ($iArc=1; $iArc<$nArcs; $iArc++) {
			$rArc = $disappearingTransitions[$iArc];
			$lArc = $disappearingTransitions[$iArc-1];
			if ($rArc->edge)
			$rArc->edge->setStartPoint($lArc->site, $rArc->site, $vertex);
		}
	
		// create a new edge as we have now a new transition between
		// two beach sections which were previously not adjacent.
		// since this edge appears as a new vertex is defined, the vertex
		// actually define an end point of the edge (relative to the site
		// on the left)
		$lArc = $disappearingTransitions[0];
		$rArc = $disappearingTransitions[$nArcs-1];
		$rArc->edge = $this->createEdge($lArc->site, $rArc->site, null, $vertex);
	
		// create circle events if any for beach sections left in the beachline
		// adjacent to collapsed sections
		$this->attachCircleEvent($lArc);
		$this->attachCircleEvent($rArc);
	}
	
	public function addBeachsection ($site) 
	{
		$x = $site->x;
		$directrix = $site->y;
	
		// find the left and right beach sections which will surround the newly
		// created beach section.
		// rhill 2011-06-01: This loop is one of the most often executed;
		// hence we expand in-place the comparison-against-epsilon calls.
		$node = $this->_beachline->_root;
		$lArc = null;
		$rArc = null;
	
		while ($node) {
			$dxl = $this->leftBreakPoint($node,$directrix)-$x;
			// x lessThanWithEpsilon xl => falls somewhere before the left edge of the beachsection
			if ($dxl > self::EPSILON) {
				if (!$node->left) {
			        $rArc = $node->left;
					break;
				} else {
				    $node = $node->left;
				}
			}
			else {
				$dxr = $x-$this->rightBreakPoint($node,$directrix);
				// x greaterThanWithEpsilon xr => falls somewhere after the right edge of the beachsection
				if ($dxr > self::EPSILON) {
					if (!$node->right) {
						$lArc = $node;
						break;
					}
					
					$node = $node->right;
				}
				else {
					// x equalWithEpsilon xl => falls exactly on the left edge of the beachsection
					if ($dxl > -self::EPSILON) {
						$lArc = $node->previous;
						$rArc = $node;
					}
					// x equalWithEpsilon xr => falls exactly on the right edge of the beachsection
					else if ($dxr > -self::EPSILON) {
						$lArc = $node;
						$rArc = $node->next;
					}
					// falls exactly somewhere in the middle of the beachsection
					else {
						$lArc = $rArc = $node;
					}
					
					break;
				}
			}
		}
		
		// at this point, keep in mind that lArc and/or rArc could be
		// undefined or null.
	
		// create a new beach section object for the site and add it to RB-tree
		$newArc = $this->createBeachsection($site);
		$this->_beachline->insertSuccessor($lArc, $newArc);
	
		// cases:
		//
	
		// [null,null]
		// least likely case: new beach section is the first beach section on the
		// beachline.
		// This case means:
		//   no new transition appears
		//   no collapsing beach section
		//   new beachsection become root of the RB-tree
		if (!$lArc && !$rArc) {
			return;
		}
	
		// [lArc,rArc] where lArc == rArc
		// most likely case: new beach section split an existing beach
		// section.
		// This case means:
		//   one new transition appears
		//   the left and right beach section might be collapsing as a result
		//   two new nodes added to the RB-tree
		if ($lArc === $rArc) {
			// invalidate circle event of split beach section
			$this->detachCircleEvent($lArc);
	
			// split the beach section into two separate beach sections
			$rArc = $this->createBeachsection($lArc->site);
			$this->_beachline->insertSuccessor($newArc, $rArc);
	
			// since we have a new transition between two beach sections;
			// a new edge is born
			$newArc->edge = $rArc->edge = $this->createEdge($lArc->site, $newArc->site, null, null);
	
			// check whether the left and right beach sections are collapsing
			// and if so create circle events, to be notified when the point of
			// collapse is reached.
			$this->attachCircleEvent($lArc);
			$this->attachCircleEvent($rArc);
			
			return;
		}
	
		// [lArc,null]
		// even less likely case: new beach section is the *last* beach section
		// on the beachline -- this can happen *only* if *all* the previous beach
		// sections currently on the beachline share the same y value as
		// the new beach section.
		// This case means:
		//   one new transition appears
		//   no collapsing beach section as a result
		//   new beach section become right-most node of the RB-tree
		if ($lArc && !$rArc) {
			$newArc->edge = $this->createEdge($lArc->site, $newArc->site, null, null);
			return;
		}
	
		// [null,rArc]
		// impossible case: because sites are strictly processed from top to bottom;
		// and left to right, which guarantees that there will always be a beach section
		// on the left -- except of course when there are no beach section at all on
		// the beach line, which case was handled above.
		// rhill 2011-06-02: No point testing in non-debug version
		//if (!lArc && rArc) {
		//	throw "Voronoi.addBeachsection(): What is this I don't even";
		//	}
		if (!$lArc && $rArc) {
			throw new Voronoi_Exception(
				'It must be never appears.'
			);
		}
	
		// [lArc,rArc] where lArc != rArc
		// somewhat less likely case: new beach section falls *exactly* in between two
		// existing beach sections
		// This case means:
		//   one transition disappears
		//   two new transitions appear
		//   the left and right beach section might be collapsing as a result
		//   only one new node added to the RB-tree
		if ($lArc !== $rArc) {
			// invalidate circle events of left and right sites
			$this->detachCircleEvent($lArc);
			$this->detachCircleEvent($rArc);
	
			// an existing transition disappears, meaning a vertex is defined at
			// the disappearance point.
			// since the disappearance is caused by the new beachsection, the
			// vertex is at the center of the circumscribed circle of the left;
			// new and right beachsections.
			// http://mathforum.org/library/drmath/view/55002.html
			// Except that I bring the origin at A to simplify
			// calculation
			$lSite = $lArc->site;
			$ax = $lSite->x;
			$ay = $lSite->y;
			$bx=$site->x-$ax;
			$by=$site->y-$ay;
			$rSite = $rArc->site;
			$cx=$rSite->x-$ax;
			$cy=$rSite->y-$ay;
			$d=2*($bx*$cy-$by*$cx);
			$hb=$bx*$bx+$by*$by;
			$hc=$cx*$cx+$cy*$cy;
			$vertex = new Nurbs_Point(($cy*$hb-$by*$hc)/$d+$ax, ($bx*$hc-$cx*$hb)/$d+$ay);
	
			// one transition disappear
			$rArc->edge->setStartPoint($lSite, $rSite, $vertex);
	
			// two new transitions appear at the new vertex location
			$newArc->edge = $this->createEdge($lSite, $site, null, $vertex);
			$rArc->edge = $this->createEdge($site, $rSite, null, $vertex);
	
			// check whether the left and right beach sections are collapsing
			// and if so create circle events, to handle the point of collapse.
			$this->attachCircleEvent($lArc);
			$this->attachCircleEvent($rArc);
			
			return;
		}
	}
	
	public function attachCircleEvent ($arc) 
	{
		$lArc = $arc->previous;
		$rArc = $arc->next;
		
		if (!$lArc || !$rArc) {
			return;
		} // does that ever happen?
		
		$lSite = $lArc->site;
		$cSite = $arc->site;
		$rSite = $rArc->site;
	
		// If site of left beachsection is same as site of
		// right beachsection, there can't be convergence
		if ($lSite===$rSite) {
			return;
		}
	
		// Find the circumscribed circle for the three sites associated
		// with the beachsection triplet.
		// rhill 2011-05-26: It is more efficient to calculate in-place
		// rather than getting the resulting circumscribed circle from an
		// object returned by calling Voronoi.circumcircle()
		// http://mathforum.org/library/drmath/view/55002.html
		// Except that I bring the origin at cSite to simplify calculations.
		// The bottom-most part of the circumcircle is our Fortune 'circle
		// event', and its center is a vertex potentially part of the final
		// Voronoi diagram.
		$bx = $cSite->x;
		$by = $cSite->y;
		$ax = $lSite->x-$bx;
		$ay = $lSite->y-$by;
		$cx = $rSite->x-$bx;
		$cy = $rSite->y-$by;
	
		// If points l->c->r are clockwise, then center beach section does not
		// collapse, hence it can't end up as a vertex (we reuse 'd' here, which
		// sign is reverse of the orientation, hence we reverse the test.
		// http://en.wikipedia.org/wiki/Curve_orientation#Orientation_of_a_simple_polygon
		// rhill 2011-05-21: Nasty finite precision error which caused circumcircle() to
		// return infinites: 1e-12 seems to fix the problem.
		$d = 2*($ax*$cy-$ay*$cx);
		if ($d >= -2e-12) {
			return;
		}
	
		$ha = $ax*$ax+$ay*$ay;
		$hc = $cx*$cx+$cy*$cy;
		$x = ($cy*$ha-$ay*$hc)/$d;
		$y = ($ax*$hc-$cx*$ha)/$d;
		$ycenter = $y+$by;
	
		// Important: ybottom should always be under or at sweep, so no need
		// to waste CPU cycles by checking
	
		// recycle circle event object if possible
		$circleEvent = array_pop($this->_circleEventJunkyard);
		if (!$circleEvent) {
			$circleEvent = new CircleEvent();
		}
		
		$circleEvent->arc = $arc;
		$circleEvent->site = $cSite;
		$circleEvent->x = $x+$bx;
		$circleEvent->y = $ycenter+sqrt($x*$x+$y*$y); // y bottom
		$circleEvent->ycenter = $ycenter;
		$arc->circleEvent = $circleEvent;
	
		// find insertion point in RB-tree: circle events are ordered from
		// smallest to largest
		$predecessor = null;
		$node = $this->_circleEvents->_root;
		
		while ($node) {
			if ($circleEvent->y < $node->y || ($circleEvent->y === $node->y && $circleEvent->x <= $node->x)) {
				if ($node->left) {
					$node = $node->left;
				}
				else {
					$predecessor = $node->previous;
					break;
				}
			}
			else {
				if ($node->right) {
					$node = $node->right;
				}
				else {
					$predecessor = $node;
					break;
				}
			}
		}
		
		$this->_circleEvents->insertSuccessor($predecessor, $circleEvent);
		if (!$predecessor) {
			$this->_firstCircleEvent = $circleEvent;
		}
	}
	
	public function detachCircleEvent ($arc) 
	{
		$circle = $arc->circleEvent;
		
		if ($circle) {
			if (!$circle->previous) {
				$this->_firstCircleEvent = $circle->next;
			}
			
			$this->_circleEvents->removeNode($circle); // remove from RB-tree
			$this->_circleEventJunkyard[] = $circle;
			$arc->circleEvent = null;
		}
	}
	
	public function connectEdge ($edge, $bbox) 
	{
		// skip if end point already connected
		$vb = $edge->vb;
		if (!!$vb) {
			return true;
		}
	
		// make local copy for performance purpose
		$va = $edge->va;
		$xl = $bbox->xl;
		$xr = $bbox->xr;
		$yt = $bbox->yt;
		$yb = $bbox->yb;
		$lSite = $edge->p1;
		$rSite = $edge->p2;
		$lx = $lSite->x;
		$ly = $lSite->y;
		$rx = $rSite->x;
		$ry = $rSite->y;
		$fx = ($lx+$rx)/2;
		$fy = ($ly+$ry)/2;
		$fm = null;
	
		// get the line equation of the bisector if line is not vertical
		if ($ry !== $ly) {
			$fm = ($lx-$rx)/($ry-$ly);
			$fb = $fy-$fm*$fx;
		}
	
		// remember, direction of line (relative to left site):
		// upward: left.x < right.x
		// downward: left.x > right.x
		// horizontal: left.x == right.x
		// upward: left.x < right.x
		// rightward: left.y < right.y
		// leftward: left.y > right.y
		// vertical: left.y == right.y
	
		// depending on the direction, find the best side of the
		// bounding box to use to determine a reasonable start point
	
		// special case: vertical line
		if ($fm === null) {
			// doesn't intersect with viewport
			if ($fx < $xl || $fx >= $xr) {
				return false;
			}
			
			// downward
			if ($lx > $rx) {
				if (!$va) {
					$va = new Nurbs_Point($fx, $yt);
				}
				else if ($va->y >= $yb) {
					return false;
				}
				
				$vb = new Nurbs_Point($fx, $yb);
			}
			// upward
			else {
				if (!$va) {
					$va = new Nurbs_Point($fx, $yb);
				}
				else if ($va->y < $yt) {
					return false;
				}
				
				$vb = new Nurbs_Point($fx, $yt);
			}
		}
		// closer to vertical than horizontal, connect start point to the
		// top or bottom side of the bounding box
		else if ($fm < -1 || $fm > 1) {
			// downward
			if ($lx > $rx) {
				if (!$va) {
					$va = new Nurbs_Point(($yt-$fb)/$fm, $yt);
				}
				else if ($va->y >= $yb) {
					return false;
				}
				
				$vb = new Nurbs_Point(($yb-$fb)/$fm, $yb);
			}
			// upward
			else {
				if (!$va) {
					$va = new Nurbs_Point(($yb-$fb)/$fm, $yb);
				}
				else if ($va->y < $yt) {
					return false;
				}
				
				$vb = new Nurbs_Point(($yt-$fb)/$fm, $yt);
			}
		}
		// closer to horizontal than vertical, connect start point to the
		// left or right side of the bounding box
		else {
			// rightward
			if ($ly < $ry) {
				if (!$va) {
					$va = new Nurbs_Point($xl, $fm*$xl+$fb);
				}
				else if ($va->x >= $xr) {
					return false;
				}
				
				$vb = new Nurbs_Point($xr, $fm*$xr+$fb);
			}
			// leftward
			else {
				if (!$va) {
					$va = new Nurbs_Point($xr, $fm*$xr+$fb);
				}
				else if ($va->x < $xl) {
					return false;
				}
				
				$vb = new Nurbs_Point($xl, $fm*$xl+$fb);
			}
		}
		
		$edge->va = $va;
		$edge->vb = $vb;
		
		return true;
	}
	
	// line-clipping code taken from:
	//   Liang-Barsky function by Daniel White
	//   http://www.skytopia.com/project/articles/compsci/clipping.html
	// Thanks!
	// A bit modified to minimize code paths
	public function clipEdge ($edge, $bbox) 
	{
		$ax = $edge->va->x;
		$ay = $edge->va->y;
		$bx = $edge->vb->x;
		$by = $edge->vb->y;
		$t0 = 0;
		$t1 = 1;
		$dx = $bx-$ax;
		$dy = $by-$ay;
		
		// left
		$q = $ax-$bbox->xl;
		if ($dx===0 && $q<0) {
			return false;
		}
		if( $dx != 0 ) {
			$r = -$q/$dx;
			if ($dx<0) {
				if ($r<$t0) {
					return false;
				}
				else if ($r<$t1) {
					$t1=$r;
				}
			}
			else if ($dx>0) {
				if ($r>$t1) {
					return false;
				}
				else if ($r>$t0) {
					$t0=$r;
				}
			}
		}
		
		// right
		$q = $bbox->xr-$ax;
		if ($dx===0 && $q<0) {
			return false;
		}
		if( $dx != 0 ) {
			$r = $q/$dx;
			if ($dx<0) {
				if ($r>$t1) {
					return false;
				}
				else if ($r>$t0) {
					$t0=$r;
				}
			}
			else if ($dx>0) {
				if ($r<$t0) {
					return false;
				}
				else if ($r<$t1) {
					$t1=$r;
				}
			}
		}

		// top
		$q = $ay-$bbox->yt;
		if ($dy===0 && $q<0) {
			return false;
		}
		if( $dy != 0 ) {
			$r = -$q/$dy;
			if ($dy<0) {
				if ($r<$t0) {
					return false;
				}
				else if ($r<$t1) {
					$t1=$r;
				}
			}
			else if ($dy>0) {
				if ($r>$t1) {
					return false;
				}
				else if ($r>$t0) {
					$t0=$r;
				}
			}
		}
		
		// bottom		
		$q = $bbox->yb-$ay;
		if ($dy===0 && $q<0) {
			return false;
		}
		if( $dy != 0 ) {
			$r = $q/$dy;
			if ($dy<0) {
				if ($r>$t1) {
					return false;
				}
				else if ($r>$t0) {
					$t0=$r;
				}
			}
			else if ($dy>0) {
				if ($r<$t0) {
					return false;
				}
				else if ($r<$t1) {
					$t1=$r;
				}
			}
		}	
		// if we reach this point, Voronoi edge is within bbox
	
		// if t0 > 0, va needs to change
		// rhill 2011-06-03: we need to create a new vertex rather
		// than modifying the existing one, since the existing
		// one is likely shared with at least another edge
		if ($t0 > 0) {
			$edge->va = new Nurbs_Point($ax+$t0*$dx, $ay+$t0*$dy);
		}
	
		// if t1 < 1, vb needs to change
		// rhill 2011-06-03: we need to create a new vertex rather
		// than modifying the existing one, since the existing
		// one is likely shared with at least another edge
		if ($t1 < 1) {
			$edge->vb = new Nurbs_Point($ax+$t1*$dx, $ay+$t1*$dy);
		}
	
		return true;
	}
	
	// Connect/cut edges at bounding box
	public function clipEdges ($bbox) 
	{
		// connect all dangling edges to bounding box
		// or get rid of them if it can't be done
		$iEdge = count($this->_edges);
	
		// iterate backward so we can splice safely
		while ($iEdge--) {
			$edge = $this->_edges[$iEdge];
			// edge is removed if:
			//   it is wholly outside the bounding box
			//   it is actually a point rather than a line
			if (!$this->connectEdge($edge, $bbox) || !$this->clipEdge($edge, $bbox) || (abs($edge->va->x-$edge->vb->x)<self::EPSILON && abs($edge->va->y-$edge->vb->y)<self::EPSILON)) {
				$edge->va = $edge->vb = null;
				array_splice($this->_edges, $iEdge,1);
			}
		}
	}
	
	// Close the cells.
	// The cells are bound by the supplied bounding box.
	// Each cell refers to its associated site, and a list
	// of halfedges ordered counterclockwise.
	public function closeCells ($bbox) 
	{
		// prune, order halfedges, then add missing ones
		// required to close cells
		$xl = $bbox->xl;
		$xr = $bbox->xr;
		$yt = $bbox->yt;
		$yb = $bbox->yb;
		$iCell = count($this->_cells);
		$cells = $this->_cells;
		
		while ($iCell--) {
			$cell = $cells[$iCell];
			
			// trim non fully-defined halfedges and sort them counterclockwise
			if (!$cell->prepare()) {
				continue;
			}
			
			// close open cells
			// step 1: find first 'unclosed' point, if any.
			// an 'unclosed' point will be the end point of a halfedge which
			// does not match the start point of the following halfedge
			$nHalfedges = count($cell->_halfedges);
			
			// special case: only one site, in which case, the viewport is the cell
			// ...
			// all other cases
			$iLeft = 0;
			
			while ($iLeft < $nHalfedges) {
				$iRight = ($iLeft+1) % $nHalfedges;
				$endpoint = $cell->_halfedges[$iLeft]->getEndpoint();
				$startpoint = $cell->_halfedges[$iRight]->getStartpoint();
				
				// if end point is not equal to start point, we need to add the missing
				// halfedge(s) to close the cell
				if ((abs($endpoint->x-$startpoint->x)>=self::EPSILON || abs($endpoint->y-$startpoint->y)>=self::EPSILON)) {
					// if we reach this point, cell needs to be closed by walking
					// counterclockwise along the bounding box until it connects
					// to next halfedge in the list
					$va = $endpoint;
					
					// walk downward along left side
					if ($this->equalWithEpsilon($endpoint->x,$xl) && $this->lessThanWithEpsilon($endpoint->y,$yb)) {
						$vb = new Nurbs_Point($xl, $this->equalWithEpsilon($startpoint->x,$xl) ? $startpoint->y : $yb);
					}
					// walk rightward along bottom side
					else if ($this->equalWithEpsilon($endpoint->y,$yb) && $this->lessThanWithEpsilon($endpoint->x,$xr)) {
						$vb = new Nurbs_Point($this->equalWithEpsilon($startpoint->y,$yb) ? $startpoint->x : $xr, $yb);
					}
					// walk upward along right side
					else if ($this->equalWithEpsilon($endpoint->x,$xr) && $this->greaterThanWithEpsilon($endpoint->y,$yt)) {
						$vb = new Nurbs_Point($xr, $this->equalWithEpsilon($startpoint->x,$xr) ? $startpoint->y : $yt);
					}
					// walk leftward along top side
					else if ($this->equalWithEpsilon($endpoint->y,$yt) && $this->greaterThanWithEpsilon($endpoint->x,$xl)) {
						$vb = new Nurbs_Point($this->equalWithEpsilon($startpoint->y,$yt) ? $startpoint->x : $xl, $yt);
					}
					
					if ($vb) {
					
						$edge = $this->createBorderEdge($cell->_site, $va, $vb);
						$newHalfedge = new Halfedge($edge, $cell->_site, null);
						
						array_splice($cell->_halfedges, $iLeft+1, 0, array($newHalfedge));
						$nHalfedges = count($cell->_halfedges);
					}
				}
				
				$iLeft++;
			}
		}
	}
	
	public function equalWithEpsilon ($a,$b){return abs($a-$b)<self::EPSILON;}
	public function greaterThanWithEpsilon ($a,$b){return $a-$b>self::EPSILON;}
	public function greaterThanOrEqualWithEpsilon ($a,$b){return $b-$a<self::EPSILON;}
	public function lessThanWithEpsilon ($a,$b){return $b-$a>self::EPSILON;}
	public function lessThanOrEqualWithEpsilon ($a,$b){return $a-$b<self::EPSILON;}
}

class Voronoi_Exception extends Exception
{}

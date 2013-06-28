<?php 
class Halfedge
{
	public $site;
	public $edge;
	public $angle;
	
	/**
	 * Constructeur.
	 * 
	 * Note: entre les classes Edge et Halfedge, il y a deux choses similaires qui
	 * n'ont pas le même nom:
	 * 	- lSite == p1
	 * 	- rSite == p2
	 */
	public function __construct ($edge, $lSite, $rSite)
	{
		$this->site = $lSite;
		$this->edge = $edge;
		
		// 'angle' is a value to be used for properly sorting the
		// halfsegments counterclockwise. By convention, we will
		// use the angle of the line defined by the 'site to the left'
		// to the 'site to the right'.
		// However, border edges have no 'site to the right': thus we
		// use the angle of line perpendicular to the halfsegment (the
		// edge should have both end points defined in such case.)
		if ($rSite) {
			$this->angle = atan2($rSite->y-$lSite->y, $rSite->x-$lSite->x);
		}
		else {
			$va = $edge->va;
			$vb = $edge->vb;
			
			// rhill 2011-05-31: used to call getStartpoint()/getEndpoint(),
			// but for performance purpose, these are expanded in place here.
			$this->angle = ($edge->p1 === $lSite) ? atan2($vb->x-$va->x, $va->y-$vb->y)
			                                  : atan2($va->x-$vb->x, $vb->y-$va->y);
			}
	}

	public function getStartpoint () 
	{
		return $this->edge->p1 === $this->site ? $this->edge->va : $this->edge->vb;
	}

	public function getEndpoint () 
	{
		return $this->edge->p1 === $this->site ? $this->edge->vb : $this->edge->va;
	}
}
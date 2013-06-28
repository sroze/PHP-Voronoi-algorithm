<?php 
class Cell
{
	public $_site;
	public $_halfedges;
	
	public function __construct ($site)
	{
		$this->_site = $site;
		$this->_halfedges = array();
	}
	
	public function prepare ()
	{
		$this->_halfedges = $this->_halfedges;
		$iHalfedge = count($this->_halfedges);
		$edge = null;
		
		// get rid of unused halfedges
		// rhill 2011-05-27: Keep it simple, no point here in trying
		// to be fancy: dangling edges are a typically a minority.
		while ($iHalfedge--) {
			$edge = $this->_halfedges[$iHalfedge]->edge;
			if (!$edge->vb || !$edge->va) {
				array_splice($this->_halfedges, $iHalfedge, 1);
			}
		}
		
		// rhill 2011-05-26: I tried to use a binary search at insertion
		// time to keep the array sorted on-the-fly (in Cell.addHalfedge()).
		// There was no real benefits in doing so, performance on
		// Firefox 3.6 was improved marginally, while performance on
		// Opera 11 was penalized marginally.
		usort($this->_halfedges, function($a, $b){
			$r = $b->angle - $a->angle;
			// sroze 2011-07-05: fix for PHP sorting. Must be here else
			// it won't sort correctly and then make some problems.
			return ($r < 0) ? floor($r) : ceil($r);
		});
		
		return count($this->_halfedges);
	}
}
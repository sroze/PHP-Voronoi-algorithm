<?php 
/**
 * Représente un point défini par ses trois coordonnées.
 * 
 */
class Nurbs_Point
{
	// Coordonnées du point
	public $x;
	public $y;
	public $z;
	
	// ID du point
	public $id;
	
	public $halfedges = array();
	
	/**
	 * Constructeur.
	 * 
	 */
	public function __construct ($x, $y, $z = null)
	{
		// On stocke les valeurs
		$this->x = $x;
		$this->y = $y;
		$this->z = $z;
	}
	
	/**
	 * Associe un ID au point.
	 * 
	 */
	public function setId ($id)
	{
		$this->id = $id;
		return $this;
	}
	
	/**
	 * Regarde si deux points sont identiques.
	 * 
	 */
	public function equals (Nurbs_Point $point)
	{
		return ($this->x == $point->x && $this->y == $point->y && (($this->z == null && $point->z == null) || $this->z == $point->z));
	}
	
	public function __toString ()
	{
		return '('.$this->x.','.$this->y.($this->z != null ? ','.$this->z : '').')';
	}
}
<?php 
class Nurbs_Vector extends Nurbs_Point
{
	/**
	 * Créé un vecteur à partir de deux points.
	 * 
	 * @return Nurbs_Vector
	 */
	public static function fromPoints (Nurbs_Point $p1, Nurbs_Point $p2)
	{
		return new self(
			$p2->x - $p1->x,
			$p2->y - $p1->y,
			(($p1->z != null && $p2->z != null) ? $p2->z - $p1->z : null)
		);
	}
	
	/**
	 * Calcul le produit scalaire entre le vecteur et un autre.
	 * 
	 * @see http://fr.wikipedia.org/wiki/Produit_scalaire#.C3.89criture_matricielle
	 * 
	 * @return double
	 */
	public function produitScalaire (Nurbs_Vector $vector)
	{
		$scal_z = ($this->z != null && $vector->z != null) ? $this->z * $vector->z : 0;
		return ($this->x * $vector->x + $this->y * $vector->y + $scal_z);
	}
	
	/**
	 * Calcul le produit vectoriel entre le vecteur et un autre.
	 * 
	 * @see http://homeomath.imingo.net/prodvect.htm
	 * 
	 * @return Nurbs_Vector
	 */
	public function produitVectoriel (Nurbs_Vector $vector)
	{
		return new Nurbs_Vector(
			$this->y * $vector->z - $this->z * $vector->y,
			$this->z * $vector->x - $this->x * $vector->z,
			$this->x * $vector->y - $this->y * $vector->x
		);
	}
}
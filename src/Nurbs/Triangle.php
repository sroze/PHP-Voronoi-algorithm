<?php
/**
 * Représente un triangle de Delaunay.
 * 
 */ 
class Nurbs_Triangle extends Nurbs_Surface_Abstract
{
	/**
	 * On stocke les points du triangle.
	 * 
	 */
	public $p1;
	public $p2;
	public $p3;
	
	/**
	 * Créé le triangle, avec trois points.
	 * 
	 */
	public function __construct (Nurbs_Point $p1, Nurbs_Point $p2, Nurbs_Point $p3)
	{
		// On ajoutes les points à la surface.
		$this->addPoints(array($p1, $p2, $p3));
		
		// On les stocke également en local
		$this->p1 = $p1;
		$this->p2 = $p2;
		$this->p3 = $p3;
	}
	
	/**
	 * Vérifie que le triangle est valide.
	 * 
	 */
	public function isValid ()
	{
		$epsilon = pow(1, -15);
		
		return !(abs($this->p1->y - $this->p2->y) < $epsilon && abs($this->p2->y - $this->p3->y) < $epsilon);
	}
	
	/**
	 * Test si le point donné est dans le cercle circonscrit du
	 * triangle.
	 * 
	 */
	public function pointInCircle (Nurbs_Point $point)
	{
		// On vérifie que le triangle est valide
		if (!$this->isValid()) {
			return false;
		}
		
		// On calcul la valeur "0" arrondie
		$epsilon = pow(1, -15);
		
		if (abs($this->p2->y - $this->p1->y) < $epsilon) {
			$m2 = -($this->p3->x - $this->p2->x) / ($this->p3->y - $this->p2->y);
			$mx2 = ($this->p2->x + $this->p3->x) * 0.5;
			$my2 = ($this->p2->y + $this->p3->y) * 0.5;
			
			//Calculate CircumCircle center (xc,yc)
			$xc = ($this->p2->x + $this->p1->x) * 0.5;
			$yc = $m2 * ($xc - $mx2) + $my2;
		} else if (abs($this->p3->y - $this->p2->y) < $epsilon) {
			$m1 = -($this->p2->x - $this->p1->x) / ($this->p2->y - $this->p1->y);
			$mx1 = ($this->p1->x + $this->p2->x) * 0.5;
			$my1 = ($this->p1->y + $this->p2->y) * 0.5;
			$xc = ($this->p3->x + $this->p2->x) * 0.5;
			$yc = $m1 * ($xc - $mx1) + $my1;
		} else {
			$m1 = -($this->p2->x - $this->p1->x) / ($this->p2->y - $this->p1->y);
			$m2 = -($this->p3->x - $this->p2->x) / ($this->p3->y - $this->p2->y);
			$mx1 = ($this->p1->x + $this->p2->x) * 0.5;
			$mx2 = ($this->p2->x + $this->p3->x) * 0.5;
			$my1 = ($this->p1->y + $this->p2->y) * 0.5;
			$my2 = ($this->p2->y + $this->p3->y) * 0.5;
			$xc = ($m1 * $mx1 - $m2 * $mx2 + $my2 - $my1) / ($m1 - $m2);
			$yc = $m1 * ($xc - $mx1) + $my1;
		}

		$dx = $this->p2->x - $xc;
		$dy = $this->p2->y - $yc;
		$rsqr = $dx * $dx + $dy * $dy;

		//double r = Math.Sqrt(rsqr); //Circumcircle radius
		$dx = $point->x - $xc;
		$dy = $point->y - $yc;
		$drsqr = $dx * $dx + $dy * $dy;
		
		return ($drsqr <= $rsqr);
	}
	
	/**
	 * Regarde si le point est inclus dans le triangle.
	 * 
	 * Note: utilise la technique du barycentre.
	 * 
	 * @return bool
	 */
	public function pointIn (Nurbs_Point $point)
	{
		// On calcul les vecteurs
		$v0 = Nurbs_Vector::fromPoints($this->p3, $this->p1);
		$v1 = Nurbs_Vector::fromPoints($this->p2, $this->p1);
		$v2 = Nurbs_Vector::fromPoints($point, $this->p1);
		
		// On calcul le produit scalaire des vecteurs
		$dot00 = $v0->produitScalaire($v0);
		$dot01 = $v0->produitScalaire($v1);
		$dot02 = $v0->produitScalaire($v2);
		$dot11 = $v1->produitScalaire($v1);
		$dot12 = $v1->produitScalaire($v2);
		
		// On calcul les coordonnées du barycentre
		$invDenom = 1 / ($dot00 * $dot11 - $dot01 * $dot01);
		$u = ($dot11 * $dot02 - $dot01 * $dot12) * $invDenom;
		$v = ($dot00 * $dot12 - $dot01 * $dot02) * $invDenom;
		
		// On vérifie que le point est bien dans le triangle
		return ($u > 0 && $v > 0 && (($u + $v) < 1));
	}
	
	/**
	 * Retourne un rectangle englobant le triangle.
	 * 
	 * @return array[Nurbs_Point 1, Nurbs_Point 2]
	 */
	public function getRect ()
	{
		$x1 = min(array($this->p1->x, $this->p2->x, $this->p3->x));
		$y1 = min(array($this->p1->y, $this->p2->y, $this->p3->y));
		$x2 = max(array($this->p1->x, $this->p2->x, $this->p3->x));
		$y2 = max(array($this->p1->y, $this->p2->y, $this->p3->y));
		
		return array(new Nurbs_Point($x1, $y1), new Nurbs_Point($x2, $y2));
	}
	
	/**
	 * Récupère le point aux coordonnées (x, y) sur le plan.
	 * 
	 * @see http://fr.wikipedia.org/wiki/Plan_%28math%C3%A9matiques%29#D.C3.A9finition_par_un_vecteur_normal_et_un_point
	 * 
	 * @return Nurbs_Point
	 */
	public function getPoint ($x, $y)
	{
		// On construit le point
		$point = new Nurbs_Point($x, $y, 0);
		
		// On cherche le vecteur normal au plan du triangle
		$v_triangle_normal = $this->getNormalVector();
		
		// On calcul la valeur "d" de l'équation du plan
		$d = - ($v_triangle_normal->x * $this->p1->x + $v_triangle_normal->y * $this->p1->y + $v_triangle_normal->z * $this->p1->z);
		
		// Grâce à l'équation du plan, on calcul la valeur de Z
		$point->z = (-$v_triangle_normal->x * $point->x - $v_triangle_normal->y * $point->y - $d) / ($v_triangle_normal->z);
		echo $point->z.'/'.$d."\n";
		
		return $point;
	}
	
	/**
	 * Retourne le vecteur normal au plan défini par le triangle.
	 * 
	 * @see http://fr.wikipedia.org/wiki/Plan_%28math%C3%A9matiques%29#D.C3.A9finition_par_deux_vecteurs_et_un_point
	 * 
	 * @return Nurbs_Vector
	 */
	public function getNormalVector ()
	{
		// On va calculer deux vecteurs partant de A
		$v1 = Nurbs_Vector::fromPoints($this->p2, $this->p1);
		$v2 = Nurbs_Vector::fromPoints($this->p3, $this->p1);
		
		// On va maintenant calculer le vecteur normal grâce au produit
		// vectoriel.
		return $v2->produitVectoriel($v1);
	}
}
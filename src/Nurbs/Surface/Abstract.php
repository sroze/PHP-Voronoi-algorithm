<?php
/**
 * Représente une surface.
 * 
 */ 
abstract class Nurbs_Surface_Abstract
{
	/**
	 * Liste des points de la surface.
	 * 
	 * @var array of Nurbs_Point
	 */
	protected $_points = array();
	
	/**
	 * Ajoutes des points.
	 * 
	 * @param array $points
	 */
	public function addPoints (array $points)
	{
		$this->_points = $this->_points + $points;
	}
	
	/**
	 * Retourne la hauteur d'un point.
	 * 
	 * @param integer $x
	 * @param integer $y
	 * @param double $radius On ne garde que les X points les plus prêts
	 * @return Nurbs_Point
	 */
	public function getPoint ($x, $y, $radius = 3)
	{
		// On va calculer la distance sur le plan entre le point demandé
		// et les X autres plus près et connus.
		$distances = array();
		foreach ($this->_points as $point) {
			$distance = (int) (sqrt(abs($point->x - $x) + abs($point->y - $y)) * 100);
			
			// Si la distance est égale à 0, c'est le même point, on retourne
			// donc le point
			if ($distance == 0) {
				return $point;
			}
			
			// On ajoutes le point
			if (array_key_exists($distance, $distances)) {
				$distance += 1;
			}
			$distances[$distance] = $point;
		}
		
		// On va sélectionner uniquement les X points les plus prêts.
		ksort($distances);
		
		$points = array();
		$distance_min = null;
		$distance_max = null;
		$i = 0;
		foreach ($distances as $distance => $point) {
			// On ajoutes le points
			$points[] = array(
				'distance' => $distance,
				'point' => $point
			);
			
			// On calcul les distances les plus ou moins grandes
			if ($distance_min === NULL OR $distance_min > $distance) {
				$distance_min = $distance;
			}
			if ($distance_max === NULL OR $distance_max < $distance) {
				$distance_max = $distance;
			}
			
			// On ne prend que les X premiers, où X = $radius
			if (++$i >= $radius) {
				break;
			}
		}
		
		// On va calculer les pondérations
		$ponderations = array();
		$ponderations_sum = 0;
		foreach ($points as $distance) {
			// On calcul la pondération
			$ponderation = $this->getPonderation($distance['distance'], $distance_min, $distance_max);
			$ponderations_sum += $ponderation;
			
			// On ajoutes le point et sa pondération au tableau
			$ponderations[] = array(
				'point' => $distance['point'],
				'ponderation' => $ponderation
			);
		}
		
		// On va maintenant utiliser les propriétés des barycentres pour
		// calculer la hauteur du point
		$sum = 0;
		foreach ($ponderations as $ponderation) {
			$sum += $ponderation['ponderation'] * $ponderation['point']->z;
		}
		
		// On calcul la hauteur
		$z = $sum / $ponderations_sum;
		
		// On retourne le point calculé
		return new Nurbs_Point($x, $y, $z);
	}
	
	/**
	 * Transforme une distance en pondération. Permet d'égaliser comme on le souhaite.
	 * 
	 */
	public function getPonderation ($distance, $min, $max)
	{
		return ($max / $min) * $distance + $max;
	}
	
	/**
	 * Retourne la liste des points.
	 * 
	 */
	public function getPoints ()
	{
		return $this->_points;
	}
}
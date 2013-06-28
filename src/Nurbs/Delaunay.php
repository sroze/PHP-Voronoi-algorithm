<?php 
class Nurbs_Delaunay
{
	/**
	 * Triangule les points.
	 * 
	 * @param array of Nurbs_Point
	 * @return array of Nurbs_Triangle
	 */
	public static function triangulate (array $points)
	{
		// On enregistre le nom de points
		$nv = count($points);
		
		// On calcul le nombre maximal de triangles
		$trimax = $nv * 4;
		
		// On recherche les bords exterieurs des points
		$xmin = $points[0]->x;
		$ymin = $points[0]->y;
		$xmax = $xmin;
		$ymax = $ymin;
		
		for ($i = 0; $i < count($points); $i++) {
			// On ajoutes un ID au point et on le récupère
			$point = $points[$i]->setId($i);
		
			// On compare ses coordonnées aux limites
			if ($point->x < $xmin) {
				$xmin = $point->z;
			} else if ($point->x > $xmax) {
				$xmax = $point->x;
			}
			
			if ($point->y < $ymin) {
				$ymin = $point->y;
			} else if ($point->y > $ymax) {
				$ymax = $point->y;
			}
		}
		
		// On calcul les différences de coordonnées max
		$dx = $xmax - $xmin;
		$dy = $ymax - $ymin;
		
		// On créé ensuite le super-triangle, qui contient tous les points.
		$dmax = ($dx > $dy) ? $dx : $dy;
		$xmid = ($xmax + $xmin) / 2;
		$ymid = ($ymax + $ymin) / 2;
		
		// On ajoutes nos trois points
		$p1 = new Nurbs_Point(($xmid - 2 * $dmax), ($ymid - $dmax));
		$p2 = new Nurbs_Point($xmid, ($ymid + 2 * $dmax));
		$p3 = new Nurbs_Point(($xmid + 2 * $dmax), ($ymid - $dmax));
		
		// On ajoutes des Id aux points
		$p1->setId($nv+1);
		$p2->setId($nv+2);
		$p3->setId($nv+3);
		
		// On ajoutes ces points à la liste
		$points[] = $p1;
		$points[] = $p2;
		$points[] = $p3;
		
		// On créé la liste des triangles et on ajoutes le super-triangle
		$triangles = array();
		$triangles[] = new Nurbs_Triangle($p1, $p2, $p3);
		
		// On ajoutes les points 1 par 1
		for ($i = 0; $i < $nv; $i++) {
			$edges = array();
			
			// Set up the edge buffer.
			// If the point (Vertex(i).x,Vertex(i).y) lies inside the circumcircle then the
			// three edges of that triangle are added to the edge buffer and the triangle is removed from list.
			for ($j = 0; $j < count($triangles); $j++) {
				// On teste si le point est dans le cercle circonscrit du triangle
				if ($triangles[$j]->pointInCircle($points[$i])) {
					$edges[] = new Nurbs_Edge($triangles[$j]->p1, $triangles[$j]->p2);
					$edges[] = new Nurbs_Edge($triangles[$j]->p2, $triangles[$j]->p3);
					$edges[] = new Nurbs_Edge($triangles[$j]->p3, $triangles[$j]->p1);
					
					// On supprime le triangle
					array_splice($triangles, $j, 1);
					$j--;
				}
			}
		
			// Si le dernier point enlevée était le dernier du tableau
			if ($i >= $nv) {
				continue;
			}
			
			// On supprime les lines dupliquées
			for ($j = count($edges) - 2; $j >= 0; $j--) {
				for ($k = count($edges) - 1; $k >= $j + 1; $k--) {
					// Si les deux lignes sont égales, on les supprime
					if ($edges[$j]->equals($edges[$k])) {
						array_splice($edges, $k, 1);
						array_splice($edges, $j, 1);
						$k--;
					}
				}
			}
			
			// On créé de nouveaux triangles pour le point courant
			for ($j = 0; $j < count($edges); $j++) {
				if (count($triangles) >= $trimax) {
					echo "Nombre maximum de edges dépassé";
				}
				
				// On ajoutes le triangle
				$triangles[] = new Nurbs_Triangle($edges[$j]->p1, $edges[$j]->p2, $points[$i]);
			}
			
			// On purge la liste des edges
			$edges = array();
		}
		
		// On retire les triangles ayant des bords du super triangle
		for ($i = count($triangles) - 1; $i >= 0; $i--) {
			if ($triangles[$i]->p1->id >= $nv || $triangles[$i]->p2->id >= $nv || $triangles[$i]->p3->id >= $nv) {
				array_splice($triangles, $i, 1);
			}
		}
		
		// On retourne le tableau des triangles
		return $triangles;
	}
}
<?php 
require_once '../library/Nurbs/Voronoi.php';
require_once '../library/Nurbs/Point.php';

$bbox = new stdClass();
$bbox->xl = 0;
$bbox->xr = 400;
$bbox->yt = 0;
$bbox->yb = 400;

$xo = 0;
$dx = $width = 400;
$yo = 0;
$dy = $height = 400;
$n = 20;
$sites = array();

// On créé l'image
$im = imagecreatetruecolor($width, $height);
$white = imagecolorallocate($im, 255, 255, 255);
$red = imagecolorallocate($im, 255, 0, 0);
$green = imagecolorallocate($im, 0, 100, 0);
$black = imagecolorallocate($im, 0, 0, 0);
imagefill($im, 0, 0, $white);
//imageantialias($im, true);

// On créé des points aléatoires que l'on dessine
for ($i=0; $i < $n; $i++) {
	$point = new Nurbs_Point(rand($xo, $dx), rand($yo, $dy));
	$sites[] = $point;
	
	imagerectangle($im, $point->x - 2, $point->y - 2, $point->x + 2, $point->y + 2, $black);
}

$voronoi = new Voronoi();
$diagram = $voronoi->compute($sites, $bbox);
//var_dump($diagram);

//var_dump('sites', $sites, 'diagram', $diagram, 'cells', $diagram['cells']);//, 'voronoi', $voronoi);
$j = 0;
foreach ($diagram['cells'] as $cell) {
	// On va agreger les points du polygone.
	$points = array();
	
	if (count($cell->_halfedges) > 0) {
		$v = $cell->_halfedges[0]->getStartPoint();
		if ($v) {
			$points[] = $v->x;
			$points[] = $v->y;
		} else {
			var_dump($j.': no start point');
		}
		
		for ($i = 0; $i < count($cell->_halfedges); $i++) {
			$halfedge = $cell->_halfedges[$i];
			$edge = $halfedge->edge;
			
			if ($edge->va && $edge->vb) {
				imageline($im, $edge->va->x, $edge->va->y, $edge->vb->x, $edge->vb->y, $red);
			}
			
			$v = $halfedge->getEndPoint();
			if ($v) {
				$points[] = $v->x;
				$points[] = $v->y;
			} else {
				var_dump($j.': no end point #'.$i);
			}
		}
	}
	
	// On construit le polygone
	$color = imagecolorallocatealpha($im, rand(0, 255), rand(0, 255), rand(0, 255), 50);
	imagefilledpolygon($im, $points, count($points) / 2, $color);
	$j++;
}

// On affiche l'image
imagepng($im, 'voronoi.png');


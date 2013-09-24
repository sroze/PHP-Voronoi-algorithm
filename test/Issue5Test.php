<?php
require_once '../src/Nurbs/Voronoi.php';
require_once '../src/Nurbs/Point.php';
require_once '../bin/phpunit.phar';

/**
 * A PHPUnit test for GitHub issue #5
 * 
 * @name "should never appear" exception
 * @link https://github.com/sroze/PHP-Voronoi-algorithm/issues/5
 */
class Issue5Test extends PHPUnit_Framework_TestCase
{
    /**
     * Test diagram creation without infinite loop.
     * The maximum execution time is 60 seconds.
     * 
     * @param width Width of diagram
     * @param height Height of diagram
     * @param multitype:multitype:integer points The diagram points
     * 
     * @dataProvider provider
     */
    public function testVoronoi($width, $height, $basic_points)
    {
        // Create border box
        $bbox = new stdClass();
        $bbox->xl = 0;
        $bbox->xr = $width;
        $bbox->yt = 0;
        $bbox->yb = $height;

        // Create points
        $points = array();
        foreach ($basic_points as $basic_point) {
        	$points[] = new Nurbs_Point($basic_point[0], $basic_point[1]);
        }

        // Create diagram
        $voronoi = new Voronoi();
        $diagram = $voronoi->compute($points, $bbox);
        
        $this->assertTrue(is_array($diagram));
    }
    
    /**
     * Read the CSV data file.
     * 
     * @return multitype:multitype:multitype:integer
     */
    public function provider()
    {
        // Read points
        $file = file('data/issue5.dat');
        $points = array();
        foreach ($file as $line) {
            list($x, $y) = explode(',', trim($line));
            
            $points[] = array($x, $y);
        }
        
        return array(
            array(10000, 10000, $points),
        );
    }
}
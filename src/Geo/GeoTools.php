<?php

/**
 * Jin Framework
 * Diatem
 */

namespace Jin2\Geo;

/**
 * Outils de calculs géographiques divers
 */
class GeoTools
{

  const GEO_UNIT_METERS = 'm';
  const GEO_UNIT_KILOMETERS = 'km';

  const EARTH_RADIUS = 6378137;

  /**
   * Retourne la distance, en kilomètres ou en m, entre deux géopositions en tenant compte de la courbure du globe terrestre
   *
   * @param  float $lat1   Latitute point A
   * @param  float $lng1   Longitude point A
   * @param  float $lat2   Latitude point B
   * @param  float $lng2   Longitude point B
   * @param  string $unite Unité. (km ou m)
   * @return float
   */
  public static function getDistanceBetweenTwoLatLng($lat1, $lng1, $lat2, $lng2, $unite = self::GEO_UNIT_METERS)
  {
    $earth_radius = self::EARTH_RADIUS;
    $rlo1 = deg2rad($lng1);
    $rla1 = deg2rad($lat1);
    $rlo2 = deg2rad($lng2);
    $rla2 = deg2rad($lat2);
    $dlo = ($rlo2 - $rlo1) / 2;
    $dla = ($rla2 - $rla1) / 2;
    $a = (sin($dla) * sin($dla)) + cos($rla1) * cos($rla2) * (sin($dlo) * sin($dlo));
    $d = 2 * atan2(sqrt($a), sqrt(1 - $a));

    if ($unite == self::GEO_UNIT_METERS) {
      return ($earth_radius * $d);
    } else if($unite == self::GEO_UNIT_KILOMETERS) {
      return (($earth_radius * $d) / 1000);
    } else {
      throw new \Exception('Unité non reconnue');
    }
  }

}

<?php

/**
 * Framework JIN
 */

namespace Jin2\Geo\Google\Maps\CustomGMapper;

use Jin2\Geo\Google\Maps\GeoProjectionMercator;
use Jin2\Image\Image;
use Jin2\Image\ImagePart;
use Jin2\Image\Filters\AbsoluteResize;
use Jin2\Image\Filters\ImageImport;
use Jin2\Image\Filters\Opacity;
use Jin2\Image\Filters\RectangleFill;

/**
 * Classe permettant le mapping d'une image en tuiles GoogleMap
 */
class CustomGoogleMapper
{

  /**
   * @var integer  Niveau de zoom
   */
  protected $zoom;

  /**
   * @var string  Chemin de l'image à mapper
   */
  protected $imagePath;

  /**
   * @var GeoZone  Représentation géographique des coordonnées de l'image
   */
  protected $imageGeoZone;

  /**
   * @var GeoZone  Représentation géographique de la zone valide. (le reste devant être masqué). Si Null pas  de masquage
   */
  protected $valideGeoZone;

  /**
   * @var integer  Couleur de masquage (en dehors de la zone valide). Composante rouge.
   */
  protected $masquageColorR;

  /**
   * @var integer  Couleur de masquage (en dehors de la zone valide). Composante verte.
   */
  protected $masquageColorG;

  /**
   * @var integer  Couleur de masquage (en dehors de la zone valide). Composante bleue.
   */
  protected $masquageColorB;

  /**
   * @var GeoZone  Représentation géographique des coordonnées des tuiles recouvertes par l'image au niveau de zoom spécifié
   */
  protected $tilesGeoZone;

  /**
   * @var array  Tuile SudOuest {'x' => integer, 'y' => integer}
   */
  protected $soTile;

  /**
   * @var array   integer}}
   */
  protected $soTileInfo;

  /**
   * @var array  Tuile NordEst {'x' => integer, 'y' => integer}
   */
  protected $neTile;

  /**
   * @var array  Infos tuile NoedEst {'min' => {'lat' => integer, 'long' => integer}, 'max' => {'lat' => integer, 'long' => integer}}
   */
  protected $neTileInfo;

  /**
   * @var integer  Largeur de l'image de sortie avant découpe
   */
  protected $outputWidth;

  /**
   * @var integer  Hauteur de l'image de sortie avant découpe
   */
  protected $outputHeight;

  /**
   * @var integer  Nombre de tuiles en largeur
   */
  protected $nbTilesX;

  /**
   * @var integer  Nombre de tuiles en hauteur
   */
  protected $nbTilesY;

  /**
   * @var type  Taille h/w de la tuile de sortie en pixel (256 pour GoogleMap)
   */
  protected $tileZize = 256;

  /**
   * @var \Jin2\external\gmap\GeoProjectionMercator  Objet GeoProjectionMercator
   */
  protected static $gpm;

  /**
   * Constructeur
   *
   * @param integer  $zoom       Niveau de zoom
   * @param string   $imagePath  Chemin de l'image à mapper
   * @param integer  $lat1       Latitude du point A de l'image
   * @param integer  $lat2       Latitude du point B de l'image
   * @param integer  $lon1       Longitude du point A de l'image
   * @param integer  $lon2       Longitude du point B de l'image
   */
  public function __construct($zoom, $imagePath, $lat1, $lat2, $lon1, $lon2)
  {
    $this->zoom = $zoom;
    $this->imagePath = $imagePath;
    $this->imageGeoZone = new GeoZone($lat1, $lat2, $lon1, $lon2);

    self::$gpm = new GeoProjectionMercator($this->tileZize);

    $this->calculateLimitTiles();
    $this->calculateOutputSize();
  }

  /**
   * Définit une zone de masquage. On remplit de la couleur indiquée les zones situées en dehors de la zone indiquée.
   *
   * @param integer $lat1  Latitude du point A de la zone "valide"
   * @param integer $lat2  Latitude du point B de la zone "valide"
   * @param integer $lon1  Longitude du point A de la zone "valide"
   * @param integer $lon2  Longitude du point B de la zone "valide"
   * @param integer $r     Couleur de remplissage. Composante rouge.
   * @param integer $g     Couleur de remplissage. Composante verte.
   * @param integer $b     Couleur de remplissage. Composante bleue.
   */
  public function setMaskZone($lat1, $lat2, $lon1, $lon2, $r, $g, $b)
  {
    $this->valideGeoZone = new GeoZone($lat1, $lat2, $lon1, $lon2);
    $this->masquageColorR = $r;
    $this->masquageColorG = $g;
    $this->masquageColorB = $b;
  }

  /**
   * Calcule les emplacements des tuiles limites SUDOUEST et NORDEST
   */
  protected function calculateLimitTiles()
  {
    $so = $this->imageGeoZone->getSudOuestPoint();
    $this->soTile = self::$gpm->LatLonToTile($so->getLatitude(), $so->getLongitude(), $this->zoom);
    $ne = $this->imageGeoZone->getNordEstPoint();
    $this->neTile = self::$gpm->LatLonToTile($ne->getLatitude(), $ne->getLongitude(), $this->zoom);

    $this->soTileInfo = self::$gpm->TileLatLonBounds($this->soTile['x'], $this->soTile['y'], $this->zoom);
    $this->soTileInfo['min']['lat'] = $this->soTileInfo['min']['lat']*-1;
    $this->soTileInfo['max']['lat'] = $this->soTileInfo['max']['lat']*-1;
    $this->neTileInfo = self::$gpm->TileLatLonBounds($this->neTile['x'], $this->neTile['y'], $this->zoom);
    $this->neTileInfo['min']['lat'] = $this->neTileInfo['min']['lat']*-1;
    $this->neTileInfo['max']['lat'] = $this->neTileInfo['max']['lat']*-1;
    $this->tilesGeoZone = new GeoZone(
      $this->soTileInfo['max']['lat'], $this->neTileInfo['min']['lat'],
      $this->soTileInfo['min']['lon'], $this->neTileInfo['max']['lon']
    );
  }

  /**
   * Calcule la taille de l'image de sortie avant découpe
   */
  protected function calculateOutputSize()
  {
    $this->nbTilesX = (max($this->neTile['x'], $this->soTile['x']) - min($this->neTile['x'], $this->soTile['x']) + 1);
    $this->nbTilesY = (max($this->neTile['y'], $this->soTile['y']) - min($this->neTile['y'], $this->soTile['y']) + 1);
    $this->outputWidth = $this->nbTilesX * $this->tileZize;
    $this->outputHeight = $this->nbTilesY * $this->tileZize;
  }

  /**
   * Retourne la position X de la tuile SO
   *
   * @return integer
   */
  public function getMinTileX()
  {
    return $this->soTile['x'];
  }

  /**
   * Retourne la position X de la tuile NE
   *
   * @return integer
   */
  public function getMaxTileX()
  {
    return $this->neTile['x'];
  }

  /**
   * Retourne la position Y de la tuile NE
   *
   * @return integer
   */
  public function getMinTileY()
  {
    return $this->neTile['y'];
  }

  /**
   * Retourne la position Y de la tuile SO
   *
   * @return integer
   */
  public function getMaxTileY()
  {
    return $this->soTile['y'];
  }

  /**
   * Effectue la sortie de l'ensemble des tuiles
   *
   * @param string $outputFolder                  Chemin du dossier de sortie des tuiles
   * @param string $fileNameTemplate              Template des noms de tuile générées. (Par défaut %zoom%_%tilex%_%tiley%.%ext%)
   * @param boolean $createFolderIfNotExists      Crée le dossier si il n'existe pas (TRUE par défaut)
   * @param boolean $deleteFolderContentBefore    Supprime le contenu du dossier avant génération des données (FALSE par défaut)
   * @param integer $opacity                      Opacité des tuiles - de 1 à 100. (100 par défaut)
   */
  public function build($outputFolder, $fileNameTemplate = '%zoom%_%tilex%_%tiley%.%ext%', $createFolderIfNotExists = true, $deleteFolderContentBefore = false, $opacity = 100)
  {
    // Gestion du dossier de sortie
    if (!is_dir($outputFolder) && !$createFolderIfNotExists) {
      throw new \Exception('Le dossier ' . $outputFolder . ' n\'existe pas');
    } else if (!is_dir($outputFolder) && $createFolderIfNotExists) {
      mkdir($outputFolder);
    }
    if ($deleteFolderContentBefore) {
      $files = glob($outputFolder . '*');
      foreach ($files as $file) {
        if (is_file($file)) {
          unlink($file);
        }
      }
    }

    // Calcul taille image à inserer
    $aso = $this->tilesGeoZone->getSudOuestPoint();
    $ane = new GeoPoint(max($this->soTileInfo['min']['lat'], $this->soTileInfo['max']['lat']), max($this->soTileInfo['min']['lon'], $this->soTileInfo['max']['lon']));
    $a = $this->imageGeoZone->getSudOuestPoint();

    $leftPixelMargin = floor($this->tileZize * (($a->getLongitude() - $aso->getLongitude()) / ($ane->getLongitude() - $aso->getLongitude())));
    $bottomPixelMargin = floor($this->tileZize * (($a->getLatitude() - $aso->getLatitude()) / ($ane->getLatitude() - $aso->getLatitude())));

    $bso = new GeoPoint(min($this->neTileInfo['min']['lat'], $this->neTileInfo['max']['lat']), min($this->neTileInfo['min']['lon'], $this->neTileInfo['max']['lon']));
    $bne = $this->tilesGeoZone->getNordEstPoint();
    $b = $this->imageGeoZone->getNordEstPoint();

    $rightPixelMargin = $this->tileZize - floor($this->tileZize * (($b->getLongitude() - $bso->getLongitude()) / ($bne->getLongitude() - $bso->getLongitude())));
    $topPixelMargin = $this->tileZize - floor($this->tileZize * (($b->getLatitude() - $bso->getLatitude()) / ($bne->getLatitude() - $bso->getLatitude())));

    $xTargetSize = (($this->nbTilesX - 2) * $this->tileZize) + ($this->tileZize - $leftPixelMargin) + ($this->tileZize - $rightPixelMargin);
    $yTargetSize = (($this->nbTilesY - 2) * $this->tileZize) + ($this->tileZize - $topPixelMargin) + ($this->tileZize - $bottomPixelMargin);

    //Preparation d el'image source aux bonnes dimensions
    $sourceImage = new Image($this->imagePath);
    $resizeFilter = new AbsoluteResize($xTargetSize, $yTargetSize);
    $sourceImage->addFilter($resizeFilter);

    //Generation de l'image full definition
    $full = new Image(null, $this->outputWidth, $this->outputHeight);
    $imageImport = new ImageImport(null, $sourceImage, $leftPixelMargin, $topPixelMargin);
    $full->addFilter($imageImport);
    if($opacity < 100){
      $imageOpacity = new Opacity($opacity);
      $full->addFilter($imageOpacity);
    }

    //Si masquage, on crée les rectangles nécessaires
    if ($this->valideGeoZone) {
      $y_c1 = $this->valideGeoZone->getNordOuestPoint();
      $y_c2 = $this->valideGeoZone->getSudOuestPoint();
      $y_b = $this->tilesGeoZone->getNordOuestPoint();
      $y_a = $this->tilesGeoZone->getSudOuestPoint();
      $topMargin = round($this->outputHeight * (($y_b->getLatitude() - $y_c1->getLatitude()) / ($y_b->getLatitude() - $y_a->getLatitude())));
      $bottomMargin = round($this->outputHeight - ($this->outputHeight * (($y_b->getLatitude() - $y_c2->getLatitude()) / ($y_b->getLatitude() - $y_a->getLatitude()))));

      $x_c1 = $this->valideGeoZone->getNordOuestPoint();
      $x_c2 = $this->valideGeoZone->getNordEstPoint();
      $x_a = $this->tilesGeoZone->getNordOuestPoint();
      $x_b = $this->tilesGeoZone->getNordEstPoint();
      $leftMargin = round($this->outputWidth * (($x_c1->getLongitude() - $x_a->getLongitude()) / ($x_b->getLongitude() - $x_a->getLongitude())));
      $rightMargin = round($this->outputWidth - ($this->outputWidth * (($x_c2->getLongitude() - $x_a->getLongitude()) / ($x_b->getLongitude() - $x_a->getLongitude()))));

      //Masque top
      $rectangleTop = new RectangleFill(0, 0, $this->outputWidth, $topMargin, $this->masquageColorR, $this->masquageColorG, $this->masquageColorB);
      $full->addFilter($rectangleTop);

      //Masque bottom
      $rectangleBottom = new RectangleFill(0, ($this->outputHeight - $bottomMargin), $this->outputWidth, $this->outputHeight, $this->masquageColorR, $this->masquageColorG, $this->masquageColorB);
      $full->addFilter($rectangleBottom);

      //Masque left
      $rectangleLeft = new RectangleFill(0, 0, $leftMargin, $this->outputHeight, $this->masquageColorR, $this->masquageColorG, $this->masquageColorB);
      $full->addFilter($rectangleLeft);

      //Masque right
      $rectangleRight = new RectangleFill(($this->outputWidth - $rightMargin), 0, $this->outputWidth, $this->outputHeight, $this->masquageColorR, $this->masquageColorG, $this->masquageColorB);
      $full->addFilter($rectangleRight);
    }

    $startTileY = $this->soTile['y'] - $this->nbTilesY + 1;
    $tileX = $this->soTile['x'];

    for ($x = 0; $x < $this->nbTilesX; $x++) {
      $tileY = $startTileY;

      for ($y = 0; $y < $this->nbTilesY; $y++) {
        $ip = $full->getImagePart($x * $this->tileZize, $y * $this->tileZize, $this->tileZize, $this->tileZize);
        $fileName = $fileNameTemplate;
        $fileName = str_replace('%zoom%', $this->zoom, $fileName);
        $fileName = str_replace('%tilex%', $tileX, $fileName);
        $fileName = str_replace('%tiley%', $tileY, $fileName);
        $fileName = str_replace('%ext%', $full->getExtension(), $fileName);

        $ip->write($outputFolder.$fileName);
        $tileY++;
      }

      $tileX++;
    }
  }

}

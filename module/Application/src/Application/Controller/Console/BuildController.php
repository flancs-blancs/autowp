<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Brand;

class BuildController extends AbstractActionController
{
    public function brandsSpriteAction()
    {
        $dir = 'public_html/img';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $destSprite = $dir . '/brands.png';
        $destCss = $dir . '/brands.css';

        $imageStorage = $this->imageStorage();

        $brandModel = new Brand();
        $brandModel->createIconsSprite($imageStorage, $destSprite, $destCss);

        return "done\n";
    }

    public function translationsAction()
    {
        $languages = ['en', 'ru', 'zh', 'de', 'fr'];

        foreach ($languages as $language) {
            $srcPath = __DIR__ . '/../../../../language/' . $language . '.php';
            $translations = include $srcPath;

            $json = \Zend\Json\Json::encode($translations);

            $dstPath = __DIR__ . '/../../../../../../assets/languages/' . $language . '.json';

            print $dstPath . PHP_EOL;
            file_put_contents($dstPath, $json);
        }

        return "done\n";
    }
}

<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Picture\Row as PictureRow;
use Application\PictureNameFormatter;

class Pic extends AbstractHtmlElement
{
    /**
     * @var PictureRow
     */
    private $picture = null;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    public function __construct(PictureNameFormatter $pictureNameFormatter)
    {
        $this->pictureNameFormatter = $pictureNameFormatter;
    }

    public function __invoke(PictureRow $picture = null)
    {
        $this->picture = $picture;

        return $this;
    }

    public function url()
    {
        if ($this->picture) {
            $identity = $this->picture->identity ? $this->picture->identity : $this->picture->id;

            return $this->view->url('picture/picture', [
                'picture_id' => $identity
            ]);
        }
        return false;
    }

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    public function htmlTitle(array $picture)
    {
        return $this->pictureNameFormatter->formatHtml($picture, $this->view->language());
    }

    public function textTitle(array $picture)
    {
        return $this->pictureNameFormatter->format($picture, $this->view->language());
    }

    public function name($pictureRow, $language)
    {
        $pictureTable = new Picture();
        $names = $pictureTable->getNameData([$pictureRow->toArray()], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$pictureRow->id];

        return $this->pictureNameFormatter->format($name, $language);
    }
}

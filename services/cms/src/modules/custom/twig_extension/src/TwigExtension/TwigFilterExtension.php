<?php

// namespace Drupal\twig_extension\TwigExtension;
namespace Drupal\twig_extension\TwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Class TwigFilterExtension.
 */
// class TwigFilterExtension extends \Twig_Extension
class TwigFilterExtension extends AbstractExtension
{
    /**
     * Declare your custom twig filter here
     *
     * @return array|\Twig_SimpleFilter[]
     */
    public function getFilters()
    {
        return [
            // new \Twig_SimpleFilter(
            new TwigFilter(
                'remove_links',
                [$this, 'removeLinks']
            )
        ];
    }

    /**
     * Remove only links from the html
     * @param $string
     *  Html as string
     *
     * @return string
     *  Filtered html
     */
    public static function removeLinks($string)
    {
        return preg_replace(
            '#<a.*?>(.*?)</a>#i',
            '\1',
            $string
        );
    }

    /**
     * {@inheritdoc}
     * @return string
     */
    public function getName()
    {
        return 'twig_extension.filter';
    }
}

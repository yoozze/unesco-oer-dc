<?php

namespace Drupal\twig_extension\TwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Class TwigFunctionExtension.
 */
// class TwigFunctionExtension extends \Twig_Extension
class TwigFunctionExtension extends AbstractExtension
{
    /**
     * Declare your custom twig extension here
     *
     * @return array|\Twig_SimpleFunction[]
     */
    public function getFunctions()
    {
        return [
            // new \Twig_SimpleFunction(
            new TwigFunction(
                'svg_icon',
                [$this, 'svgIcon'],
                ['is_safe' => array('html')]
            ),
            // new \Twig_SimpleFunction(
            new TwigFunction(
                'replace',
                [$this, 'replace'],
                ['is_safe' => array('html')]
            )
        ];
    }

    /**
     * Render SVG icon
     * @param $name
     *  Icon name
     *
     * @return array
     */
    public function svgIcon($name, $class = '')
    {
        return [
            '#type' => 'inline_template',
            '#template' => '<svg class="c-icon c-icon--{{ name }} {{ class }}" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="#{{ name }}"></use></svg>',
            '#context' => [
                'name' => $name,
                'class' => $class,
            ],
        ];
    }

    public function replace($search, $replace, $subject)
    {
        return str_replace($search, $replace, $subject);
    }

    /**
     * {@inheritdoc}
     * @return string
     */
    public function getName()
    {
        return 'twig_extension.function';
    }
}

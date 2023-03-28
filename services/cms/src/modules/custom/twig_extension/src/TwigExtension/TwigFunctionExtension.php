<?php

namespace Drupal\twig_extension\TwigExtension;

/**
 * Class TwigFunctionExtension.
 */
class TwigFunctionExtension extends \Twig_Extension
{
    /**
     * Declare your custom twig extension here
     *
     * @return array|\Twig_SimpleFunction[]
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction(
                'svg_icon',
                array($this, 'svgIcon'),
                array('is_safe' => array('html'))
            ),
            new \Twig_SimpleFunction(
                'replace',
                array($this, 'replace'),
                array('is_safe' => array('html'))
            )
        );
    }

    /**
     * Render SVG icon
     * @param $name
     *  Icon name
     *
     * @return array
     */
    public function svgIcon($name)
    {
        return [
            '#type' => 'inline_template',
            '#template' => '<svg class="c-icon c-icon--{{ name }}" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="#{{ name }}"></use></svg>',
            '#context' => [
                'name' => $name,
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

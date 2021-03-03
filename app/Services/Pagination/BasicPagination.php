<?php namespace App\Services;

use Illuminate\Pagination\BootstrapThreePresenter;

class ProductPagination extends BootstrapThreePresenter
{
    /**
     * Convert the URL window into Zurb Foundation HTML.
     *
     * @return string
     */
    public function render()
    {

        if( ! $this->hasPages())
            return '';

        return sprintf('<nav class="pagination"><ul>%s</ul></nav>', trim($this->getLinks())).' '.sprintf('<nav class="pagination"><ul>%s</ul></nav>', trim($this->getLinks()));
    }

    /**
     * Get HTML wrapper for disabled text.
     *
     * @param  string  $text
     * @return string
     */
    protected function getDisabledTextWrapper($text)
    {
        return '<li class="unavailable"><a>'.$text.'</a></li>';
    }

    /**
     * Get HTML wrapper for active text.
     *
     * @param  string  $text
     * @return string
     */
    protected function getActivePageWrapper($text)
    {
        return '<li><a class="current-page">'.$text.'</a></li>';
    }

    /**
     * Get a pagination "dot" element.
     *
     * @return string
     */
    
}
<?php namespace App\Services\Pagination;

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

        return sprintf('<nav class="pagination"><ul>%s</ul></nav>', $this->getLinks()). sprintf('<nav class="pagination-next-prev"><ul>%s%s</ul></nav>', $this->getPreviousButton(), $this-> getNextButton());
    }

    /**
     * Get HTML wrapper for disabled text.
     *
     * @param  string  $text
     * @return string
     */
    protected function getDisabledTextWrapperUpdate($text, $rel)
    {
        return '<li ><a class="'.$rel.'">'.$text.'</a></li>';
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

    protected function getAvailablePageWrapper($url, $page, $rel = null)
    {       
        //$rel = is_null($rel) ? '' : ' rel="'.$rel.'"';
        return '<li><a class="'.$rel.'" href="'.htmlentities($url).'"'.$rel.'>'.$page.'</a></li>';
    }

    protected function getPreviousButton($text = '&laquo;')
    {
        if ($this->paginator->currentPage() <= 1) return $this->getDisabledTextWrapperUpdate($text, 'prev');
        $url = $this->paginator->url( $this->paginator->currentPage() - 1);
        return $this->getPageLinkWrapper($url, $text, 'prev');
    }

    /**
     * Get the next page pagination element.
     *
     * @param  string  $text
     * @return string
     */
    protected function getNextButton($text = '&raquo;')
    {
        if ( ! $this->paginator->hasMorePages()) return $this->getDisabledTextWrapperUpdate($text, 'next');
        $url = $this->paginator->url($this->paginator->currentPage() + 1);
        return $this->getPageLinkWrapper($url, $text, 'next');
    }



    /**
     * Get a pagination "dot" element.
     *
     * @return string
     */
    
}
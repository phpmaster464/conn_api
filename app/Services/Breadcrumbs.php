<?php namespace App\Services;

use Config;

class Breadcrumbs {

	private $crumbs = array();

	public function __construct ( )
	{

	}

	public function get_crumb_array()
	{
		return $this->crumbs;
	}

	public function clear()
	{
		$this->crumbs = array();
	}

	public function crumb($crumb, $link =null, $title='')
	{
		if(empty($title)) $title = $crumb;
		$this->crumbs[] = array('crumb'=>$crumb, 'link'=>$link, 'title'=>$title);
	}

	public function crumbs($glue ='&gt;')
	{
		$count = count($this->crumbs);

		if($count > 1)
		{

			$value = '<nav id="breadcrumbs">';
			$value .= '<ul>';

			$i=1;foreach($this->crumbs as $crumb)
			{

				if(is_null($crumb['link']) || $i == $count) $value .= '<li>'.$crumb['crumb'].'</li>';
				$value .= '<li><a href="'.url($crumb['link']).'">'.$crumb['crumb'].'</a></li>';
			}

			$value .= '</ul>';
			$value .= '</nav>';
			return $value;
		}
	}

	public function title_crumbs()
	{
		return Config::get('general.site_name').' | '.$this->heading_text();
	}

	public function edit_last_breadcrumb($crumb, $link=null, $title='')
	{
		$count = count($this->crumbs) -1;
		$this->crumbs[$count] = array('crumb'=>$crumb, 'link'=>$link, 'title'=>$title);
	}

	public function heading()
	{
		$heading = last($this->crumbs);
		return '<h2>'.$heading['crumb'].'</h2>';
	}

	public function heading_text()
	{
		$heading = last($this->crumbs);
		return $heading['crumb'];
	}

	public function get_crumb($i)
	{
		$crumbs =  $this->getCrumbArray();
		$count = count($crumbs);

		if($i <= $count)
		{
			$crumb = $crumbs[$i-1];
			return $crumb['crumb'];
		}
	}

	public function get_previous()
	{
		$crumbs = $this->getCrumbArray();
		$count = count($crumbs);

		if($count >= 1)
		{
			$parent = $crumbs[count($crumbs)-1];
			return $parent['link'];
		}
	}

	public function get_heading_text()
	{
		$heading = last($this->crumbs);
		return str_singular(trim($heading['crumb']));
	}
}

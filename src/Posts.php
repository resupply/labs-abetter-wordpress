<?php

namespace ABetter\Wordpress;

class Posts {

	public $args;
	public $query;
	public $posts;
	public $items;

	public static $cache;

	// --- Constructor

	public function __construct($defined_vars = []) {
		$this->args = (object) $defined_vars;
		$this->items = $this->build();
	}

	// ---

	public function build($props=NULL) {

		Controller::loadWp();

		$this->args = array_replace([
			'post_type' => 'post',
			'post_status' => 'publish',
			'orderby' => 'date',
			'order' => 'DESC',
			'numberposts' => -1,
			'suppress_filters' => TRUE,
			'fake' => FALSE,
		],(array)$this->args);

		$this->query = new \WP_Query($this->args);
		$this->posts = (!empty($this->query->posts)) ? $this->query->posts : [];
		$this->items = [];

		foreach ($this->posts AS $post) {
			$this->items[$post->ID] = $this->buildItem($post);
		}

		// ---

		if ($this->args['fake'] && count($this->items) < $this->args['numberposts']) {
			while (count($this->items) < $this->args['numberposts']) {
				$this->items[] = $this->fakeItem();
			}
		}

		return $this->items;

	}

	// ---

	public function buildItem($post) {
		if (isset(self::$cache['post'][$post->ID])) return self::$cache['post'][$post->ID];
		// ---
		$item = new \StdClass();
		$item->post = $post;
		$item->id = (int) $post->ID;
		$item->type = (string) $post->post_type;
		$item->type_label = (string) ($d = _dictionary($post->post_type.'_label',NULL,'')) ? $d : ucfirst($item->type);
		$item->author = (array) self::_wp_author($post);
		$item->category = (array) self::_wp_categories($post);
		$item->tag = (array) self::_wp_tags($post);
		$item->label = (string) reset($item->category);
		$item->status = (string) $post->post_status;
		$item->slug = (in_array($post->ID,self::_wp_fronts())) ? '/' : urldecode(get_page_uri($post));
		$item->url = (string) _relative(get_permalink($post));
		$item->order = (int) $post->menu_order;
		$item->parent = (int) $post->post_parent;
		$item->current = (string) _is_current($item->url,'current');
		$item->front = (string) _is_front($item->url,'front');
		// ---
		$item->timestamp = (int) get_the_date('U',$post);
		$item->date = (string) get_the_date('Y-m-d',$post);
		$item->time = (string) get_the_date('H:i',$post);
		$item->datetime = (string) get_the_date('Y-m-d H:i',$post);
		$item->datelong = (string) get_the_date('F j, Y',$post);
		// ---
		$item->title = (string) $post->post_title;
		$item->headline = (string) ($f = get_field('teaser_headline',$post)) ? $f : get_field('header_headline',$post);
		$item->lead = (string) ($f = get_field('teaser_excerpt',$post)) ? $f : get_field('header_lead',$post);
		$item->excerpt = (string) trim(strip_tags($item->lead));
		$item->content = (string) $post->post_content;
		if (!$item->headline && preg_match('/<h1[^>]*>([^<]+)<\/h1>(.*)/i',$item->content,$match)) {
			$item->headline = trim(strip_tags($match[1]));
			if ($match[2]) $item->content = trim($match[2]);
		}
		if (!$item->lead && preg_match('/<p[^>]*lead[^>]*>([^<]+)<\/p>(.*)/i',$item->content,$match)) {
			$item->lead = trim(strip_tags($match[1]));
			if ($match[2]) $item->content = trim($match[2]);
		}
		if (!$item->headline) $item->headline = $item->title;
		if (!$item->excerpt) $item->excerpt = _excerpt(($item->lead)?$item->lead:$item->content,400);
		// ---
		$item->image = (string) ($f = get_field('teaser_image',$post)) ? $f['url'] : (($f = get_field('header_image',$post)) ? $f['url'] : '');
		if (!$item->image && preg_match('/<img[^>]*src="([^"]+)"/i',$item->content,$match)) {
			$item->image = $match[1];
		}
		// ---
		self::$cache['post'][$post->ID] = $item;
		return $item;
	}

	// ---
	
	public function fakeItem() {
		$item = new \StdClass();
		$item->type = "fake";
		$item->url = "#fake";
		$item->label = _lipsum('label');
		$item->title = _lipsum('label');
		$item->headline = _lipsum('headline');
		$item->lead = _lipsum('lead');
		$item->excerpt = _excerpt(_lipsum('normal'),400);
		$item->image = _pixsum('photo');
		return $item;
	}

	// ---

	public static function _wp_categories($post,$terms=[]) {
		if ($get = get_the_category($post->ID)) foreach ($get AS $term) {
			$terms[$term->term_id] = ($d = _dictionary('category_'.$term->slug.'_label',NULL,'')) ? $d : $term->name;
		}
		return $terms;
	}

	public static function _wp_tags($post,$terms=[]) {
		if ($get = get_the_category($post->ID)) foreach ($get AS $term) {
			$terms[$term->term_id] = ($d = _dictionary('tag_'.$term->slug.'_label',NULL,'')) ? $d : $term->name;
		}
		return $terms;
	}

	public static function _wp_author($post,$author=[]) {
		$author[get_the_author_meta('ID',$post->post_author)] = get_the_author_meta('nickname',$post->post_author);
		return $author;
	}

	// ---

	public static function _wp_option($name) {
		if (isset(self::$cache['_wp_option_'][$name])) return self::$cache['_wp_option_'][$name];
		global $GLOBALS; $option = $GLOBALS['wpdb']->get_row('SELECT option_value FROM wp_options WHERE option_name = "'.$name.'"', ARRAY_N);
		$option = (is_array($option)) ? reset($option) : NULL;
		self::$cache['_wp_option_'][$name] = $option;
		return $option;
	}

	public static function _wp_fronts() {
		if (isset(self::$cache['_wp_fronts'])) return self::$cache['_wp_fronts'];
		$fronts = (array) self::_wp_option('page_on_front');
		//global $GLOBALS; $results = $GLOBALS['wpdb']->get_results('SELECT element_id FROM wp_icl_translations WHERE trid = "'.self::_wp_option('page_on_front').'"', ARRAY_N);
		//foreach ($results AS $row) $fronts[] = reset($row);
		self::$cache['_wp_fronts'] = $fronts;
		return $fronts;
	}

	// ---

}
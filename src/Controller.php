<?php

namespace ABetter\Wordpress;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use ABetter\Wordpress\Site;
use ABetter\Wordpress\Post;
use Closure;

class Controller extends BaseController {

	public $args = array();
	public $slug = '';
	public $languages = array();
	public $language = '';
	public $suggestions = [];
	public $template = '';
	public $post = NULL;
	public $user = NULL;
	public $error = NULL;

	public static $handle = NULL;

	// ---

	public function __construct($args=NULL) {
		self::$handle = new \StdClass();
		self::loadWp();
	}

	// ---

	public static function loadWp() {
		if (defined('ABSPATH')) return;
		define('WP_USE_THEMES', FALSE);
		require_once public_path('wp').'/wp-load.php';
	}

	// ---

	public function getDefaultLanguage() {
		return strtolower(strtok(get_bloginfo('language'),'-'));
	}

	public function getAvailableLanguages() {
		$this->languages = array($this->getDefaultLanguage());
		//$this->languages[] = 'se';
		$this->languages = array_unique($this->languages);
		return $this->languages;
	}

	public function getRequestLanguage() {
		$this->language = (!empty($this->args[0]) && in_array($this->args[0],$this->getAvailableLanguages())) ? $this->args[0] : '';
		if ($this->language) array_splice($this->args, 0, 1);
		return $this->language;
	}

	public function getRequestSlug() {
		return (!empty($this->args)) ? $this->args[count($this->args)-1] : '';
	}

	// ---

	public function isFront() {
		return (empty($this->args) || empty($this->args[0])) ? TRUE : FALSE;
	}

	public function isPosts() {
		return (($i = get_option('page_for_posts')) && $i == $this->post->ID) ? TRUE : FALSE;
	}

	public function isSitemap() {
		return (!empty($this->args[0]) && preg_match('/^sitemap/',$this->args[0])) ? TRUE : FALSE;
	}

	public function isError() {
		return (!empty($this->post->error)) ? (int) $this->post->error : FALSE;
	}

	// ---

	public function getUser() {
		return wp_get_current_user();
	}

	// ---

	public function getPost() {
		if ($this->isFront() || $this->isSitemap()) return Post::getFront();
		return Post::getPost($this->slug);
	}

	// ---

	public function getPostTemplateSuggestions() {
		$suggestions = array();
		$suggestions[] = ($this->post->post_type == 'post') ? 'page' : 'post';
		$suggestions[] = $this->post->post_type;
		$suggestions[] = $this->post->post_type.'--'.$this->post->post_name;
		if ($t = basename(get_page_template_slug($this->post->ID),'.php')) $suggestions[] = $t;
		if ($this->isPosts()) $suggestions[] = 'posts';
		if ($this->isFront()) $suggestions[] = 'front';
		if ($code = $this->isError()) {
			$suggestions[] = (string) $code;
			$suggestions[] = 'error';
		}
		$suggestions = array_reverse($suggestions);
		return $suggestions;
	}

	// ---

	public function handle() {
		$this->args = func_get_args();
		$this->user = $this->getUser();
		$this->language = $this->getRequestLanguage();
		$this->slug = $this->getRequestSlug();
		$this->post = $this->getPost();
		$this->suggestions = $this->getPostTemplateSuggestions();
		$this->error = (isset($this->post->error)) ? $this->post->error : NULL;
		$this->expire = ($expire = get_field('settings_expire',$this->post)) ? $expire : '1 hour';
		$this->redirect = ($redirect = get_field('settings_redirect',$this->post)) ? $redirect : NULL;
		self::$handle->post = $this->post;
		self::$handle->suggestions = $this->suggestions;
		self::$handle->error = $this->error;
		// ---
		if ($theme = env('WP_THEME')) {
			view()->addLocation(base_path().'/resources/views/'.$theme);
			view()->addLocation(base_path().'/vendor/abetter/wordpress/views/'.$theme);
		}
		view()->addLocation(base_path().'/vendor/abetter/wordpress/views/abetter');
		if ($this->isSitemap()) return response()->view('sitemap')->header('Content-Type','text/xml');
		foreach ($this->suggestions AS $suggestion) {
			if (view()->exists($suggestion)) {
				$this->template = $suggestion;
				self::$handle->view = $suggestion;
				return view($suggestion)->with([
					'site' => Site::getSite(),
					'post' => $this->post,
					'item' => Posts::buildPost($this->post),
					'error' => $this->error,
					'expire' => $this->expire,
					'redirect' => $this->redirect,
					'template' => $suggestion,
				]);
			}
		}
		return "No template found in views.";
    }

}

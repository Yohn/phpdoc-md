<?php
namespace Clean\PhpDocMd\Markdown\GitHub;

use Clean\View\Phtml;

/**
 * Class Readme
 * Generates a GitHub-flavored Markdown README using a PHTML template.
 */
class Readme extends Phtml {
	/**
	 * @var \stdClass Stores table of contents links.
	 */
	private $links;

	/**
	 * Readme constructor.
	 *
	 * @param string $title The title of the README.
	 */
	public function __construct($title) {
		$this->links = new \stdClass;
		$this->set('title', $title);
		$this->set('toc', $this->links);
		$this->setTemplate(__DIR__ . '/../../../tpl/github.readme.phtml');
	}

	/**
	 * Add a link to the table of contents.
	 *
	 * @param string $name The display name of the link.
	 * @param string $mdPath The Markdown file path or anchor.
	 * @return $this
	 */
	public function addLink($name, $mdPath) {
		$this->links->$name = $mdPath;
		return $this;
	}
}

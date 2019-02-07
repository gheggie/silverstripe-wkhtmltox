<?php

namespace Grasenhiller\WkHtmlToX;

use SilverStripe\Assets\FileNameFilter;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Environment;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;

class WkHelper {

	protected $folder;
	protected $options;

	function __construct($type) {
		$this->autoSetProxy();
		$this->autoSetBinary($type);
	}


	/**
	 * Set the proxy if defined in the environment
	 */
	public function autoSetProxy() {
		$proxy = Environment::getEnv('SS_PROXY');

		if ($proxy) {
			$this->setOption('proxy', $proxy);
		}
	}

	/**
	 * Set the binary if defined in the environment
	 *
	 * @param string $type 'pdf' or 'image'
	 */
	public function autoSetBinary(string $type) {
		$binary = false;

		if ($type == 'pdf') {
			$binary = Environment::getEnv('SS_WKHTMLTOPDF_BINARY');
		} else if ($type == 'image') {
			$binary = Environment::getEnv('SS_WKHTMLTOIMAGE_BINARY');
		}

		if ($binary) {
			$this->setOption('binary', $binary);
		}
	}

	/**
	 * @param string $fileName
	 * @param string $extension
	 *
	 * @return string
	 */
	protected function generateValidFileName(string $fileName, string $extension = '') {
		$filter = FileNameFilter::create();
		$parts = array_filter(preg_split("#[/\\\\]+#", $fileName));

		$fileName = implode('/', array_map(function ($part) use ($filter) {
			return $filter->filter($part);
		}, $parts));

		if ($extension) {
			$parts = explode('.', $fileName);

			if (
				count($parts) <= 1
				|| (count($parts) > 1 && $parts[count($parts) - 1] != $extension)
			) {
				$parts[] = $extension;
			}

			$fileName = implode('.', $parts);
		}

		return $fileName;
	}

	/**
	 * @param string $fileName
	 * @param string $fileClass
	 * @param array  $extraData
	 *
	 * @return mixed
	 */
	protected function createFile(string $fileName, string $fileClass, array $extraData = []) {
		$parts = explode('.', $fileName);
		unset($parts[count($parts) - 1]);
		$title = implode('.', $parts);
		$folder = $this->getFolder();

		$data = [
			'Name' => $fileName,
			'Title' => $title,
			'ParentID' => $folder->ID,
			'FileFilename' => $folder->Filename . $fileName,
		];

		if ($member = Security::getCurrentUser()) {
			$data['OwnerID'] = $member->ID;
		}

		$file = $fileClass::create(array_merge($data, $extraData));
		$file->write();

		return $file;
	}

	/**
	 * @param string $folderName
	 */
	public function setFolder(string $folderName = 'wkhtmltox') {
		$this->folder = Folder::find_or_make($folderName);

		if (!file_exists($this->getServerPath())) {
			mkdir($this->getServerPath(), 0777, true);
		}
	}

	/**
	 * @return mixed
	 */
	public function getFolder() {
		if (!$this->folder) {
			$this->setFolder();
		}

		return $this->folder;
	}

	/**
	 * @return string
	 */
	public function getServerPath() {
		return getcwd() . DIRECTORY_SEPARATOR . ASSETS_DIR . DIRECTORY_SEPARATOR . $this->getFolder()->getFileName();
	}

	/**
	 * @param        $obj
	 * @param array  $variables
	 * @param string $template
	 * @param string $type 'Pdf' or 'Image'
	 *
	 * @return \SilverStripe\ORM\FieldType\DBHTMLText
	 */
	public static function get_html($obj, array $variables = [], string $template = '', string $type) {
		Requirements::clear();

		if (!$template) {
			$parts = explode('\\', $obj->ClassName);

			if (count($parts > 1)) {
				$last = $parts[count($parts) - 1];
				unset($parts[count($parts) - 1]);
				$parts[] = $type;
				$parts[] = $last;
				$template = implode('\\', $parts);
			} else {
				$template = $type . '\\' . $obj->ClassName;
			}
		}

		$viewer = new SSViewer($template);
		$html = $viewer->process($obj, $variables);

		return $html;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->options;
	}

	/**
	 * Overwrite all options with the given ones
	 *
	 * @param array $options
	 */
	public function setOptions(array $options) {
		$this->options = $options;
	}

	/**
	 * @param string $option
	 *
	 * @return mixed
	 */
	public function getOption(string $option) {
		$options = $this->getOptions();

		if (isset($options[$option])) {
			return $options[$option];
		}
	}

	/**
	 * Overwrite an existing or set a new option
	 *
	 * @param string $option
	 * @param string|int|bool   $value
	 */
	public function setOption(string $option, $value = false) {
		$options = $this->getOptions();

		if ($value) {
			$options[$option] = $value;
		} else {
			if (!in_array($option, $options)) {
				$options[] = $option;
			}
		}

		$this->setOptions($options);
	}

	/**
	 * @param string $option
	 */
	public function removeOption(string $option) {
		$options = $this->getOptions();

		if (isset($options[$option])) {
			unset($options[$option]);
		} else if ($key = array_search($option, $options)) {
			unset($options[$key]);
		}

		$this->setOptions($options);
	}

	/**
	 * @param array $options
	 */
	public function removeOptions(array $options) {
		foreach ($options as $option) {
			$this->removeOption($option);
		}
	}
}
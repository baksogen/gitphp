<?php
/**
 * Controller for displaying blame
 *
 * @author Christopher Han <xiphux@gmail.com>
 * @copyright Copyright (c) 2010 Christopher Han
 * @package GitPHP
 * @subpackage Controller
 */
class GitPHP_Controller_Blame extends GitPHP_ControllerBase
{

	/**
	 * Gets the template for this controller
	 *
	 * @return string template filename
	 */
	protected function GetTemplate()
	{
		if (isset($this->params['js']) && $this->params['js']) {
			return 'blamedata.tpl';
		}
		return 'blame.tpl';
	}

	/**
	 * Gets the cache key for this controller
	 *
	 * @return string cache key
	 */
	protected function GetCacheKey()
	{
		return (isset($this->params['hashbase']) ? $this->params['hashbase'] : '') . '|' . (isset($this->params['hash']) ? $this->params['hash'] : '') . '|' . (isset($this->params['file']) ? sha1($this->params['file']) : '');
	}

	/**
	 * Gets the name of this controller's action
	 *
	 * @param boolean $local true if caller wants the localized action name
	 * @return string action name
	 */
	public function GetName($local = false)
	{
		if ($local && $this->resource) {
			return $this->resource->translate('blame');
		}
		return 'blame';
	}

	/**
	 * Read query into parameters
	 */
	protected function ReadQuery()
	{
		if (isset($_GET['hb']))
			$this->params['hashbase'] = $_GET['hb'];
		else
			$this->params['hashbase'] = 'HEAD';
		if (isset($_GET['f']))
			$this->params['file'] = $_GET['f'];
		if (isset($_GET['h'])) {
			$this->params['hash'] = $_GET['h'];
		}
		if (isset($_GET['o']) && ($_GET['o'] == 'js')) {
			$this->params['js'] = true;
			$this->DisableLogging();
		}
	}

	/**
	 * Loads data for this template
	 */
	protected function LoadData()
	{
		$head = $this->GetProject()->GetHeadCommit();
		$this->tpl->assign('head', $head);

		$commit = $this->GetProject()->GetCommit($this->params['hashbase']);
		$this->tpl->assign('commit', $commit);

		if ((!isset($this->params['hash'])) && (isset($this->params['file']))) {
			$this->params['hash'] = $commit->GetTree()->PathToHash($this->params['file']);
		}
		
		$blob = $this->GetProject()->GetObjectManager()->GetBlob($this->params['hash']);
		if ($this->params['file'])
			$blob->SetPath($this->params['file']);
		$blob->SetCommit($commit);
		$this->tpl->assign('blob', $blob);

		$blame = new GitPHP_FileBlame($this->GetProject(), $commit, $this->params['file'], $this->exe);

		$this->tpl->assign('blame', $blame->GetBlame());

		if (isset($this->params['js']) && $this->params['js']) {
			return;
		}

		$this->tpl->assign('tree', $commit->GetTree());

		if ($this->config->GetValue('geshi')) {
			include_once(GITPHP_GESHIDIR . "geshi.php");
			if (class_exists('GeSHi')) {
				$geshi = new GeSHi("",'php');
				if ($geshi) {
					$lang = GitPHP_Util::GeshiFilenameToLanguage($blob->GetName());
					if (empty($lang)) {
						$lang = $geshi->get_language_name_from_extension(substr(strrchr($blob->GetName(),'.'),1));
					}
					if (!empty($lang)) {
						$geshi->enable_classes();
						$geshi->enable_strict_mode(GESHI_MAYBE);
						$geshi->set_source($blob->GetData());
						$geshi->set_language($lang);
						$geshi->set_header_type(GESHI_HEADER_PRE_TABLE);
						$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
						$output = $geshi->parse_code();

						$bodystart = strpos($output, '<td');
						$bodyend = strrpos($output, '</tr>');

						if (($bodystart !== false) && ($bodyend !== false)) {
							$geshihead = substr($output, 0, $bodystart);
							$geshifoot = substr($output, $bodyend);
							$geshibody = substr($output, $bodystart, $bodyend-$bodystart);

							$this->tpl->assign('geshihead', $geshihead);
							$this->tpl->assign('geshibody', $geshibody);
							$this->tpl->assign('geshifoot', $geshifoot);
							$this->tpl->assign('geshicss', $geshi->get_stylesheet());
							$this->tpl->assign('geshi', true);
						}
					}
				}
			}
		}
	}

}

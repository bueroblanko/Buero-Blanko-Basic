<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( !class_exists('Puc_v4p11_DebugBar_ThemePanel', false) ):

	class Puc_v4p11_DebugBar_ThemePanel extends Puc_v4p11_DebugBar_Panel {
		/**
		 * @var Puc_v4p11_Theme_UpdateChecker
		 */
		protected $updateChecker;

		protected function displayConfigHeader() {
			$this->row('Theme directory', htmlentities($this->updateChecker->directoryName));
			parent::displayConfigHeader();
		}

		protected function getUpdateFields() {
			return array_merge(parent::getUpdateFields(), array('details_url'));
		}
	}

endif;

<?php

/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2012 Unite CMS, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */

class UniteSettingsOutputUC extends UniteSettingsOutputUCWork{


	/**
	 * draw editor input
	 */
	protected function drawEditorInput($setting){

		$settingsID = UniteFunctionsUC::getVal($setting, "id");
		$name = UniteFunctionsUC::getVal($setting, "name");
		$class = self::getInputClassAttr($setting,"","",false);

		$editorParams = array();
		$editorParams['media_buttons'] = true;
		$editorParams['wpautop'] = false;
		$editorParams['editor_height'] = 200;
		$editorParams['textarea_name'] = $name;

		if(!empty($class))
			$editorParams['editor_class'] = $class;

		$addHtml = $this->getDefaultAddHtml($setting);

		$class = $this->getInputClassAttr($setting);

		$value = UniteFunctionsUC::getVal($setting, "value");

		?>
		<div class="unite-editor-setting-wrapper unite-editor-wp" <?php echo UniteProviderFunctionsUC::escAddParam($addHtml)?>>
		<?php
			wp_editor($value, $settingsID, $editorParams);
		?>
		</div>
		<?php
	}


	/**
	 * draw post picker input
	 */
	protected function drawPostPickerInput($setting){

		$id = UniteFunctionsUC::getVal($setting, "id");
		$name = UniteFunctionsUC::getVal($setting, "name");
		$value = UniteFunctionsUC::getVal($setting, "value");
		$placeholder = UniteFunctionsUC::getVal($setting, "placeholder");

		if(empty($placeholder) === true)
			$placeholder = __("Please enter post title", "unlimited-elements-for-elementor");

		$selectedPostId = "";
		$selectedPostTitle = "";

		if(empty($value) === false)
			$selectedPostId = (int)$value;

		if(empty($value) === false){
			$post = get_post($selectedPostId);

			if(empty($post) === false){
				$selectedPostId = $post->ID;
				$selectedPostTitle = $post->post_title;
			}
		}

		$class = $this->getInputClassAttr($setting, "", "unite-setting-post-picker");
		$addHtml = $this->getDefaultAddHtml($setting);

		?>
		<div
			id="<?php esc_attr_e($id); ?>"
			class="unite-settings-postpicker-wrapper unite-setting-input-object unite-settings-exclude"
			data-settingtype="post"
			data-name="<?php esc_attr_e($name); ?>"
			<?php echo UniteProviderFunctionsUC::escAddParam($addHtml); ?>
		>
			<select
				<?php echo UniteProviderFunctionsUC::escAddParam($class); ?>
				data-placeholder="<?php esc_attr_e($placeholder); ?>"
				data-selected-post-id="<?php esc_attr_e($selectedPostId); ?>"
				data-selected-post-title="<?php esc_attr_e($selectedPostTitle); ?>"
			></select>
		</div>
		<?php
	}

}

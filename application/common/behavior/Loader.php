<?php

	namespace app\common\behavior;
	use think\facade\Request;
	use think\facade\Env;
	/**
	 * 初始化公用函数
	 */
	class Loader {

		/**
		 * 添加自定义函数
		 * @access public
		 * @param mixed $params  行为参数
		 * @return void
		 */
		public function run(Request $request, $params) {
			$path = Env::get('app_path') . 'common/common.php';
			if (is_file($path)) {
				require_once $path;
			}
		}

	}

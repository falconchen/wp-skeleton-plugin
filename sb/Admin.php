<?php

namespace SB;



class Admin
{

	public function __construct()
	{
		
		add_action('admin_menu', array($this, 'admin_menu'), 2);
		add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

		add_action('wp_ajax_sb_upload', [$this, 'wp_ajax_sb_upload']);
	}


	public function wp_ajax_sb_upload()
	{
		$action = $_REQUEST["do"];

		$file_id_zh = intval($_REQUEST["file_id_zh"]);
		$file_id_en = intval($_REQUEST["file_id_en"]);

		$file_id_zh = $file_id_zh ? $file_id_zh : 7481;
		$file_id_en =	$file_id_en ? $file_id_en : 7482;

		$is_test = intval($_REQUEST["is_test"]) > 0;
		$set_status = in_array($_REQUEST['set_status'],['private','publish']) ? $_REQUEST['set_status'] :'private';

		switch ($action) {

			case "getlist":
				$res = $this->getlist($file_id_en);
				if ($is_test) { // 测试模式限制数量
					$res = array_splice($res, 0, 10, $res);
				}

				break;
			case "prepare":
				$res = $this->prepare($file_id_zh);
				break;
			case "import_item":
				$id = $_REQUEST['id'];
				$brand = $_REQUEST['brand'];
				$transient = $_REQUEST['transient'];
				$res = $this->import_item($id, $brand, $transient,$set_status);
				break;
			
				default:
					$res = ['error' => 'unknown action'];
		}


		die(json_encode($res, JSON_UNESCAPED_UNICODE));
	}


	private function get_xml_items($file_id)
	{

		$file_path = get_attached_file($file_id);
		
		$objxml = simplexml_load_file($file_path);		
		
		$arr = Common::object2array($objxml);
		$items = isset($arr['Item']) ? $arr['Item'] : [];
		return $items;
	}

	private function getlist($file_id)
	{

		$items = $this->get_xml_items($file_id);
		
		$items = array_map(function ($item) {
			return ['id' => $item['ProdId'], 'brand' => $item['ProdBrandLangName']];
		}, $items);

		return $items;
	}


	private function prepare($file_id)
	{


		$items = $this->get_xml_items($file_id);
		$name = 'sb_items_' . $file_id;
		set_transient($name, $items, DAY_IN_SECONDS);
		return ['name' => $name];
	}

	private function import_item($id, $brand, $transient,$set_status='private')
	{

		// $tab = get_post_meta(1664, 'yikes_woo_products_tabs', true);
		// var_dump($tab);exit;



		Common::create_classification();

		$items = get_transient($transient);

		$item = array_filter($items, function ($item) use ($id) {
			return $item['ProdId'] == $id;
		});

		if (empty($item)) {
			exit("-1");
		}



		$item = array_pop($item);
		$classification_slug = Common::prodcatgname2slug($item['ProdCatgName']);
		$classification = get_term_by('slug', $classification_slug, 'product_cat');

		$item['ProdBrandLangNameEn'] = $brand;
		$item['src'] = $item['ImageURL'];
		$item['title'] = $item['ProdLangName'] . ' '.$item['ProdLangSize'];

		if (!$classification) {
			$item['title'] .= '(Skip ProdCatgName ' . $item['ProdCatgName'] . ')';
			$item['error'] = "不汇入此分类";
			return $item;
		}

		$brand_cat = get_term_by('name', $brand, 'product_cat');

		if (!$brand_cat) {
			$parent_brand_cat_id = get_term_by('slug', 'brand', 'product_cat')->term_id;
			$brand_cat =  wp_insert_term(
				$brand, // the term 
				'product_cat', // the taxonomy
				array(
					'description' => 'Sub-cat ' . $item['ProdBrandLangName'],
					'slug' => $brand,
					'parent' => $parent_brand_cat_id
				)
			);
			update_term_meta($brand_cat->term_id, 'display_type', '');
			update_term_meta($brand_cat->term_id, 'thumbnail_id', 0);
		}

		$product = get_posts(array(
			'post_type' => 'product',
			'post_status' => 'any',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => '_sbn_prod_id',
					'value' => $item['ProdId'],
					'compare' => '='
				)
			)
		));

		if (!empty($product)) {
			$item['title'] .= '(Skip Exist ProdId ' . $item['ProdId'] . ')';
			$item['error'] = "跳过已汇入产品";
			return $item;
		}


		$result = $this->import_post($item, $classification->term_id, $brand_cat->term_id,$set_status);
		if(is_wp_error($result)){
			$item['error'] = $result->get_error_message();
		}else{
			$item['error'] = 'ok';
		}

		return $item;
	}

	function import_post($item, $classification_id, $brand_cat_id,$set_status="private")
	{


		//存储json 数据到postmeta ，需要用wp_slash
		//update_post_meta( $post_id, 'double_escaped_json', wp_slash( $escaped_json ) );

		$post_id = wp_insert_post(array(
			'post_title' => sprintf('%s - %s %s', $item['ProdBrandLangNameEn'], $item['ProdLangName'], $item['ProdLangSize']),
			'post_content' => $item['PhotoDescription'],
			'post_status' => $set_status,
			'post_type' => 'product',
			'post_excerpt' => '<div class="prodlangsize">容量：' . $item['ProdLangSize'] . '</div>' . $item['PhotoDescription'],
			'post_author' => 3,
			//'post_category' => array($classification_id,$brand_cat_id),
			'meta_input' => array(
				'_sku' => '',
				//'_regular_price'=> sprintf("%.2f",$item['ProdPrice']),
				'_tax_status' => 'taxable',
				'_tax_class' => '',
				'_manage_stock' => 'yes',
				'_backorders' => 'no',
				'_sold_individually' => 'no',
				'_virtual' => 'no',
				'_downloadable' => 'no',
				'_download_limit' => 0,
				'_download_expiry' =>	0,
				'_stock' => intval($item['InvQty']),
				'_stock_status' => intval($item['InvQty']) > 0 ? 'instock' : 'outofstock',

				'_price' => sprintf("%.2f", $item['SellingPrice']),

				'_sbn_prod_id' => $item['ProdId'],
				'_sbn_data' => wp_slash(json_encode($item, JSON_UNESCAPED_UNICODE)),

			)
		), true);

		if (is_wp_error($post_id)) {
			$error = $post_id;
			return $error;
		}

		wp_set_object_terms($post_id, array($classification_id, $brand_cat_id), 'product_cat');

		//图片处理

		//特色图片

		$url = sprintf('http://s.cdnsbn.com/images/products/l/%s.jpg', $item['ProdNum']);
		$image_id = media_sideload_image($url, $post_id, $item['PhotoDescription'], 'id');
		if (!is_wp_error($image_id)) {
			set_post_thumbnail($post_id, $image_id);
		}


		//相册图片
		$photo_ids = [];
		for ($i = 1; $i <= 5; $i++) {
			$url = sprintf('http://s.cdnsbn.com/images/products/l/%s-%s.jpg', $item['ProdNum'], $i);
			$photo_id = media_sideload_image($url, $post_id, null, 'id');
			if (!is_wp_error($photo_id)) {
				$photo_ids[] = $photo_id;
			} else {
				break;
			}
		}
		if (!empty($photo_ids)) {
			update_post_meta($post_id, '_product_image_gallery', implode(',', $photo_ids));
		}

		return $post_id;
	}



	function admin_enqueue_scripts($hook_suffix)
	{

		if (false === strpos($hook_suffix, 'sb-admin')) {
			return;
		}
		wp_enqueue_style('sb-admin-css', SB_URL . '/assets/css/sb-admin.css', array(), Common::get_plugin_version());
	}

	public function admin_menu()
	{
		// add_management_page(__('上载导入xml', 'sb'), __(
		//     '上载导入xml',
		//     'sb'
		// ), 'manage_options', 'sb-admin-uploads', array($this, 'management_page'));

		add_submenu_page(
			'edit.php?post_type=product', //$parent_slug
			__('StarberryNet XML汇入', 'sb'),  //$page_title
			__('StarberryNet XML', 'sb'),        //$menu_title
			'manage_options',           //$capability
			'sb-admin-uploads', //$menu_slug
			array($this, 'management_page') //$function
		);
	}

	public function management_page()
	{
?>
		<div id="message" class="updated fade" style="display:none;line-height:2.5rem;margin-left: 0;"></div>
		<script type="text/javascript">
			function setMessage(msg) {
				jQuery("#message").html(msg);
				jQuery("#message").show();
			}

			function import_xmls() {
				var args = jQuery('#xml-upload-form').serialize();

				//console.log(args);return;
				jQuery("#import_xmls").prop("disabled", true);


				setMessage("<p><?php _e('Prepare xml items to Database...', 'sb') ?></p>");

				fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded'
						},

						body: "action=sb_upload&do=prepare&" + args
					})
					.then(res => {

						if (res.ok) { // 此处加入响应状态码判断 
							//console.log("Successful")
							return res.json()
						} else {
							//console.log("Not Successful")
							throw new Error("Failed in prepare data")
						}

					})
					.then(data => {
						args += `&transient=${data.name}`;
						importing(args);
					})
					.catch(error => {
						setMessage(error);
					})

				function importing(args) {

					setMessage("<p><?php _e('Parse Products...', 'sb') ?></p>");

					jQuery.ajax({
						url: "<?php echo admin_url('admin-ajax.php'); ?>",
						type: "POST",
						data: "action=sb_upload&do=getlist&" + args,
						success: function(result) {
							var list = eval(result);
							var curr = 0;

							if (!list) {
								setMessage("<?php _e('No attachments found.', 'sb') ?>");
								jQuery("#import_xmls").prop("disabled", false);
								return;
							}

							function importItem() {

								if (curr >= list.length) {
									jQuery("#import_xmls").prop("disabled", false);
									setMessage("<?php _e('Done.', 'sb') ?>");
									return;
								}

								setMessage(<?php printf(__('"Importing item " + %s + " of " + %s + " (ProdId: "+ %s +", ProdBrandLangName[en]: " + %s + ")..."', 'sb'), "(curr+1)", "list.length", "list[curr].id", "list[curr].brand"); ?>);

								jQuery.ajax({
									url: "<?php echo admin_url('admin-ajax.php'); ?>",
									type: "POST",
									data: "action=sb_upload&do=import_item&id=" + list[curr].id + '&brand=' + list[curr].brand + '&' + args,
									success: function(result) {
										curr = curr + 1;
										if (result != '-1') {
											var result = JSON.parse(result);
											jQuery("#thumb").show();
											jQuery("#thumb-img").attr("src", result.src);
											jQuery("#product-title").text(result.title);
											if(typeof result.error !== 'undefined'){
												jQuery("#product-error").text(result.error);
											}											
											jQuery("#product-brand").text(result.ProdBrandLangNameEn);
										}
										importItem();
									}
								});
							}

							importItem();
						},
						error: function(request, status, error) {
							setMessage("<?php _e('Error', 'sb') ?>" + request.status);
						}
					});
				}

			}

			jQuery(document).ready(function($) {

				jQuery('#import_xmls').click(function() {
					import_xmls();
				});


			});

			function updateImportBtn(){
				if(jQuery(".file_id[value='']").length == 0){
						console.log('dd');
						jQuery("#import_xmls").prop("disabled", false);
				}
			}
			
		</script>

		<form id="xml-upload-form" method="post" action="" style="display:inline; float:left; padding-right:30px;">


			<h2><?php _e("StarberryNet XML汇入", 'sb') ?><span class="import_msg"></span></h2>
			<hr />
			<?php $this->fileField(); ?>



			<p>
				<label>
					汇入后产品的状态:
					<select name="set_status">
						<option value="private"><?php _e("私密", 'sb') ?></option>
						<option value="public"><?php _e("公开", 'sb') ?></option>
					</select>
					</label>					
			</p>

			<p>
				<label>
					测试模式(只汇入前10个产品):
					<select name="is_test">
						<option value="1"><?php _e("是", 'sb') ?></option>
						<option value="0"><?php _e("否", 'sb') ?></option>
					</select>
					</label>					
			</p>
			

			<p class=" "><?php _e(
						"注意: 汇入时间较长，完成前请勿关闭此页面，以免出现问题。",
						'sb'
					); ?></p>
			<p>
			<input type="button" class="button" disabled name="import_xmls" id="import_xmls" value="<?php _e('Import data', 'sb') ?>" />
				</p>
		</form>

		<div id="thumb" style="display:none;">
			<h4><?php _e('Last Product', 'sb'); ?>:</h4>

			<img id="thumb-img" />
			<p id="product-info">
				<span id="product-brand"></span>
				<span id="product-title"></span>
				<div>
				<strong id="product-error"></strong>
				</div>
			</p>

		</div>


<?php
	}

	/**
	 * 圖片選擇/上傳框
	 */
	function fileField()
	{
		if (get_bloginfo('version') >= 3.5)
			wp_enqueue_media();
		else {
			wp_enqueue_style('thickbox');
			wp_enqueue_script('thickbox');
		}
		echo '<div class="file_label"><p>英文xml</p> <input type="hidden" class="file_id" name="file_id_en" id="file_id_en" value="' . "" . '" />
			<button class="upload_file_button button">' . __('上载', 'sb') . '</button></div>
			
		';
		echo '<div class="file_label"><p>中文xml</p> <input type="hidden" class="file_id" name="file_id_zh" id="file_id_zh" value="' . "" . '" />
			<button class="upload_file_button button">' . __('上载', 'sb') . '</button></div>
			
		';
		echo "<p></p>";

		
		echo $this->script();
	}
	/**
	 * Wordpress 上傳和選擇圖片
	 */
	function script()
	{
		return '<script type="text/javascript">
			    jQuery(document).ready(function($) {
					var wordpress_ver = "' . get_bloginfo("version") . '", upload_button;
					$(".upload_file_button").click(function(event) {
						var upload_button = $(this);
                        var file_id_elem = upload_button.parent().find(".file_id");
                        var file_label = upload_button.parent();
                        var file_link = upload_button.parent().find(".file_link");

						var frame;
						if (wordpress_ver >= "3.5") {
							event.preventDefault();
							if (frame) {
								frame.open();
								return;
							}
							frame = wp.media({
					            title: "選擇档案",
					            button: {
					                text: "確定"
					            },
					            states: [
					                new wp.media.controller.Library({
					                    title: "選擇档案",
					                    library:   wp.media.query({ type: "text/xml" }),
					                    multiple:  false,
					                    date:      true,
					                    priority:  20,
					                })
					            ]
					        });
							frame.on( "select", function() {
                                if(file_link.length){
                                    file_link.remove();
                                }
							    
								// Grab the selected attachment
								var attachment = frame.state().get("selection").first();
								frame.close();
								file_id_elem.attr("value",attachment.id);

								link_text = \'<a class="file_link" href="\'+ attachment.attributes.url +\'">\' +attachment.attributes.filename +\'</a>\';
								file_label.append(link_text);
								updateImportBtn();
							});
							frame.open();
							

							
						}
						else {
							tb_show("", "media-upload.php?type=image&amp;TB_iframe=true");
							return false;
						}
					});

			    });



			</script>';
	}
}

<?php
/**
 * Admin screens: vouchers list, voucher creation, template editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BC_Admin {

	const SLUG_LIST     = 'bon-cadeau';
	const SLUG_CREATE   = 'bon-cadeau-creer';
	const SLUG_TEMPLATE = 'bon-cadeau-modele';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_action( 'admin_post_bc_save_template', array( __CLASS__, 'handle_save_template' ) );
		add_action( 'admin_post_bc_upload_font', array( __CLASS__, 'handle_upload_font' ) );
		add_action( 'admin_post_bc_generate', array( __CLASS__, 'handle_generate' ) );
		add_action( 'admin_post_bc_download', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_bc_status', array( __CLASS__, 'handle_status' ) );
		add_action( 'admin_post_bc_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_bc_email', array( __CLASS__, 'handle_email' ) );
		add_action( 'wp_ajax_bc_preview', array( __CLASS__, 'ajax_preview' ) );
	}

	public static function menu() {
		$cap = bc_capability();
		add_menu_page( __( 'Bons cadeaux', 'bon-cadeau' ), __( 'Bons cadeaux', 'bon-cadeau' ), $cap, self::SLUG_LIST, array( __CLASS__, 'page_list' ), 'dashicons-tickets-alt', 56 );
		add_submenu_page( self::SLUG_LIST, __( 'Tous les bons', 'bon-cadeau' ), __( 'Tous les bons', 'bon-cadeau' ), $cap, self::SLUG_LIST, array( __CLASS__, 'page_list' ) );
		add_submenu_page( self::SLUG_LIST, __( 'Créer un bon', 'bon-cadeau' ), __( 'Créer un bon', 'bon-cadeau' ), $cap, self::SLUG_CREATE, array( __CLASS__, 'page_create' ) );
		add_submenu_page( self::SLUG_LIST, __( 'Modèle du bon', 'bon-cadeau' ), __( 'Modèle du bon', 'bon-cadeau' ), $cap, self::SLUG_TEMPLATE, array( __CLASS__, 'page_template' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'bon-cadeau' ) ) {
			return;
		}
		wp_enqueue_style( 'bc-admin', BC_URL . 'assets/admin.css', array(), BC_VERSION );

		if ( false !== strpos( $hook, self::SLUG_TEMPLATE ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'bc-editor', BC_URL . 'assets/editor.js', array( 'jquery' ), BC_VERSION, true );

			$template = BC_Template::get();
			$fonts    = array();
			foreach ( BC_Fonts::all() as $id => $f ) {
				$fonts[ $id ] = array(
					'label' => $f['label'],
					'url'   => $f['url'],
				);
			}
			$fields = array();
			foreach ( BC_Fields::all() as $key => $f ) {
				$fields[ $key ] = array(
					'label' => $f['label'],
					'type'  => $f['type'],
				);
			}
			wp_localize_script(
				'bc-editor',
				'BC_EDITOR',
				array(
					'zones'      => $template['zones'],
					'fonts'      => $fonts,
					'fields'     => $fields,
					'samples'    => BC_Fields::samples(),
					'imageUrl'   => BC_Template::image_url( $template ),
					'lineHeight' => BC_Renderer::LINE_HEIGHT,
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'bc_preview' ),
					'i18n'       => array(
						'chooseImage' => __( 'Choisir le visuel du bon cadeau', 'bon-cadeau' ),
						'useImage'    => __( 'Utiliser ce visuel', 'bon-cadeau' ),
						'confirmDel'  => __( 'Supprimer cette zone ?', 'bon-cadeau' ),
						'previewErr'  => __( "Erreur lors de l'aperçu.", 'bon-cadeau' ),
					),
				)
			);
		}
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	private static function check( $nonce_action ) {
		if ( ! current_user_can( bc_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'bon-cadeau' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect( $page, array $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function notice() {
		$messages = array(
			'saved'      => array( 'success', __( 'Modèle enregistré.', 'bon-cadeau' ) ),
			'font'       => array( 'success', __( 'Police ajoutée.', 'bon-cadeau' ) ),
			'created'    => array( 'success', __( 'Bon cadeau créé.', 'bon-cadeau' ) ),
			'status'     => array( 'success', __( 'Statut mis à jour.', 'bon-cadeau' ) ),
			'deleted'    => array( 'success', __( 'Bon supprimé.', 'bon-cadeau' ) ),
			'mailed'     => array( 'success', __( 'E-mail envoyé.', 'bon-cadeau' ) ),
			'mailfailed' => array( 'error', __( "L'e-mail n'a pas pu être envoyé (vérifiez l'adresse et la configuration e-mail du site).", 'bon-cadeau' ) ),
		);
		$key = isset( $_GET['bc_msg'] ) ? sanitize_key( $_GET['bc_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $messages[ $key ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $messages[ $key ][0] ), esc_html( $messages[ $key ][1] ) );
		}
		$error = get_transient( 'bc_error_' . get_current_user_id() );
		if ( $error ) {
			delete_transient( 'bc_error_' . get_current_user_id() );
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $error ) );
		}
	}

	private static function error( $message ) {
		set_transient( 'bc_error_' . get_current_user_id(), $message, 60 );
	}

	private static function action_url( $action, $post_id, array $args = array() ) {
		return wp_nonce_url(
			add_query_arg( array_merge( array( 'action' => $action, 'id' => $post_id ), $args ), admin_url( 'admin-post.php' ) ),
			$action . '_' . $post_id
		);
	}

	/* ------------------------------------------------------------------ */
	/* Template editor                                                     */
	/* ------------------------------------------------------------------ */

	public static function page_template() {
		$t     = BC_Template::get();
		$fonts = BC_Fonts::all();
		?>
		<div class="wrap bc-wrap">
			<h1><?php esc_html_e( 'Modèle du bon cadeau', 'bon-cadeau' ); ?></h1>
			<?php self::notice(); ?>
			<p class="description"><?php esc_html_e( "1. Choisissez votre visuel. 2. Ajoutez des zones de texte et déplacez-les à la souris (poignée à droite pour la largeur). 3. Dans chaque zone, insérez des champs : ils seront remplis automatiquement (code, dates, numéro) ou par vous lors de la création du bon (valeur, bénéficiaire…).", 'bon-cadeau' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bc-template-form">
				<?php wp_nonce_field( 'bc_save_template' ); ?>
				<input type="hidden" name="action" value="bc_save_template" />
				<input type="hidden" name="image_id" id="bc-image-id" value="<?php echo esc_attr( $t['image_id'] ); ?>" />
				<input type="hidden" name="zones" id="bc-zones" value="" />

				<div class="bc-editor-layout">
					<div class="bc-canvas-col">
						<div class="bc-toolbar">
							<button type="button" class="button" id="bc-choose-image"><span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'Choisir le visuel', 'bon-cadeau' ); ?></button>
							<button type="button" class="button" id="bc-add-zone"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Ajouter une zone de texte', 'bon-cadeau' ); ?></button>
							<button type="button" class="button" id="bc-preview"><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Aperçu final (rendu réel)', 'bon-cadeau' ); ?></button>
						</div>
						<div class="bc-canvas" id="bc-canvas">
							<p class="bc-empty"><?php esc_html_e( 'Aucun visuel. Cliquez sur « Choisir le visuel » (JPG, PNG ou WebP — idéalement en haute résolution).', 'bon-cadeau' ); ?></p>
						</div>
					</div>

					<div class="bc-side-col">
						<div class="bc-panel" id="bc-zone-panel">
							<h2><?php esc_html_e( 'Zone sélectionnée', 'bon-cadeau' ); ?></h2>
							<p class="bc-none description"><?php esc_html_e( 'Cliquez sur une zone du visuel pour la modifier.', 'bon-cadeau' ); ?></p>
							<div class="bc-zone-fields" hidden>
								<label><?php esc_html_e( 'Texte', 'bon-cadeau' ); ?>
									<textarea id="bc-z-text" rows="3"></textarea>
								</label>
								<label><?php esc_html_e( 'Insérer un champ', 'bon-cadeau' ); ?>
									<select id="bc-z-insert">
										<option value=""><?php esc_html_e( '— choisir —', 'bon-cadeau' ); ?></option>
										<optgroup label="<?php esc_attr_e( 'Automatiques', 'bon-cadeau' ); ?>">
											<?php foreach ( BC_Fields::all() as $key => $f ) : if ( 'auto' !== $f['type'] ) { continue; } ?>
												<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f['label'] ); ?></option>
											<?php endforeach; ?>
										</optgroup>
										<optgroup label="<?php esc_attr_e( 'À remplir', 'bon-cadeau' ); ?>">
											<?php foreach ( BC_Fields::all() as $key => $f ) : if ( 'manual' !== $f['type'] ) { continue; } ?>
												<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f['label'] ); ?></option>
											<?php endforeach; ?>
										</optgroup>
									</select>
								</label>
								<div class="bc-row">
									<label><?php esc_html_e( 'Police', 'bon-cadeau' ); ?>
										<select id="bc-z-font">
											<?php foreach ( $fonts as $id => $f ) : ?>
												<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f['label'] ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								</div>
								<div class="bc-row">
									<label><?php esc_html_e( 'Taille (px)', 'bon-cadeau' ); ?>
										<input type="number" id="bc-z-size" min="4" max="1000" step="1" />
									</label>
									<label><?php esc_html_e( 'Couleur', 'bon-cadeau' ); ?>
										<input type="color" id="bc-z-color" />
									</label>
								</div>
								<div class="bc-row">
									<label><?php esc_html_e( 'Alignement', 'bon-cadeau' ); ?>
										<select id="bc-z-align">
											<option value="left"><?php esc_html_e( 'Gauche', 'bon-cadeau' ); ?></option>
											<option value="center"><?php esc_html_e( 'Centré', 'bon-cadeau' ); ?></option>
											<option value="right"><?php esc_html_e( 'Droite', 'bon-cadeau' ); ?></option>
										</select>
									</label>
									<label class="bc-check"><input type="checkbox" id="bc-z-uppercase" /> <?php esc_html_e( 'Majuscules', 'bon-cadeau' ); ?></label>
								</div>
								<div class="bc-row bc-row-4">
									<label>X % <input type="number" id="bc-z-x" min="0" max="100" step="0.1" /></label>
									<label>Y % <input type="number" id="bc-z-y" min="0" max="100" step="0.1" /></label>
									<label><?php esc_html_e( 'Largeur %', 'bon-cadeau' ); ?> <input type="number" id="bc-z-w" min="1" max="100" step="0.1" /></label>
								</div>
								<p>
									<button type="button" class="button" id="bc-z-duplicate"><?php esc_html_e( 'Dupliquer', 'bon-cadeau' ); ?></button>
									<button type="button" class="button button-link-delete" id="bc-z-delete"><?php esc_html_e( 'Supprimer la zone', 'bon-cadeau' ); ?></button>
								</p>
							</div>
						</div>

						<div class="bc-panel">
							<h2><?php esc_html_e( 'Réglages', 'bon-cadeau' ); ?></h2>
							<label><?php esc_html_e( 'Préfixe du code', 'bon-cadeau' ); ?>
								<input type="text" name="code_prefix" value="<?php echo esc_attr( $t['code_prefix'] ); ?>" />
							</label>
							<label><?php esc_html_e( 'Validité par défaut (mois, 0 = illimitée)', 'bon-cadeau' ); ?>
								<input type="number" min="0" name="validity_months" value="<?php echo esc_attr( $t['validity_months'] ); ?>" />
							</label>
							<label><?php esc_html_e( 'Champs personnalisés (un libellé par ligne)', 'bon-cadeau' ); ?>
								<textarea name="custom_fields" rows="3" placeholder="<?php esc_attr_e( "Ex. : Nombre de personnes\nAdresse de l'établissement", 'bon-cadeau' ); ?>"><?php echo esc_textarea( implode( "\n", wp_list_pluck( $t['custom_fields'], 'label' ) ) ); ?></textarea>
							</label>
							<?php if ( $t['custom_fields'] ) : ?>
								<p class="description">
									<?php foreach ( $t['custom_fields'] as $cf ) : ?>
										<code>{<?php echo esc_html( $cf['key'] ); ?>}</code>
									<?php endforeach; ?>
								</p>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Enregistrez pour pouvoir insérer les nouveaux champs dans les zones.', 'bon-cadeau' ); ?></p>
						</div>

						<div class="bc-panel">
							<h2><?php esc_html_e( 'E-mail au bénéficiaire', 'bon-cadeau' ); ?></h2>
							<label><?php esc_html_e( 'Objet', 'bon-cadeau' ); ?>
								<input type="text" name="email_subject" value="<?php echo esc_attr( $t['email_subject'] ); ?>" />
							</label>
							<label><?php esc_html_e( 'Message (les {champs} sont remplacés)', 'bon-cadeau' ); ?>
								<textarea name="email_body" rows="6"><?php echo esc_textarea( $t['email_body'] ); ?></textarea>
							</label>
						</div>

						<?php submit_button( __( 'Enregistrer le modèle', 'bon-cadeau' ), 'primary large', 'submit', false ); ?>
					</div>
				</div>
			</form>

			<div class="bc-panel bc-font-upload">
				<h2><?php esc_html_e( 'Ajouter une police (.ttf / .otf)', 'bon-cadeau' ); ?></h2>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'bc_upload_font' ); ?>
					<input type="hidden" name="action" value="bc_upload_font" />
					<input type="file" name="font" accept=".ttf,.otf" required />
					<?php submit_button( __( 'Envoyer la police', 'bon-cadeau' ), 'secondary', 'submit', false ); ?>
					<p class="description"><?php esc_html_e( "Pour utiliser la police de votre charte graphique. Pensez à enregistrer le modèle avant d'envoyer une police.", 'bon-cadeau' ); ?></p>
				</form>
			</div>

			<div class="bc-modal" id="bc-preview-modal" hidden>
				<div class="bc-modal-inner">
					<button type="button" class="button bc-modal-close">&times;</button>
					<img alt="" />
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle_save_template() {
		self::check( 'bc_save_template' );
		BC_Template::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in save().
		self::redirect( self::SLUG_TEMPLATE, array( 'bc_msg' => 'saved' ) );
	}

	public static function handle_upload_font() {
		self::check( 'bc_upload_font' );
		$result = isset( $_FILES['font'] ) ? BC_Fonts::handle_upload( $_FILES['font'] ) : new WP_Error( 'bc_font', __( 'Aucun fichier.', 'bon-cadeau' ) ); // phpcs:ignore
		if ( is_wp_error( $result ) ) {
			self::error( $result->get_error_message() );
			self::redirect( self::SLUG_TEMPLATE );
		}
		self::redirect( self::SLUG_TEMPLATE, array( 'bc_msg' => 'font' ) );
	}

	/**
	 * Live preview of the current (unsaved) zones with sample values.
	 */
	public static function ajax_preview() {
		if ( ! current_user_can( bc_capability() ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'bc_preview' );

		$t          = BC_Template::get();
		$t['image_id'] = absint( $_POST['image_id'] ?? 0 );
		$background = BC_Template::image_path( $t );
		if ( ! $background ) {
			wp_send_json_error( __( 'Choisissez d’abord un visuel.', 'bon-cadeau' ) );
		}
		$zones = json_decode( wp_unslash( (string) ( $_POST['zones'] ?? '[]' ) ), true ); // phpcs:ignore
		$zones = is_array( $zones ) ? array_map( array( 'BC_Template', 'zone' ), array_filter( $zones, 'is_array' ) ) : array();

		$img = BC_Renderer::render( $background, $zones, BC_Fields::samples() );
		if ( is_wp_error( $img ) ) {
			wp_send_json_error( $img->get_error_message() );
		}
		ob_start();
		imagejpeg( $img, null, 85 );
		$data = ob_get_clean();
		imagedestroy( $img );
		wp_send_json_success( 'data:image/jpeg;base64,' . base64_encode( $data ) );
	}

	/* ------------------------------------------------------------------ */
	/* Create a voucher                                                    */
	/* ------------------------------------------------------------------ */

	public static function page_create() {
		$t      = BC_Template::get();
		$fields = BC_Fields::all();
		$used   = array_intersect( BC_Fields::used_in_zones( $t['zones'] ), array_keys( $fields ) );
		$manual = array_filter( $used, function ( $k ) use ( $fields ) { return 'manual' === $fields[ $k ]['type']; } );
		$auto   = array_keys( array_filter( $fields, function ( $f ) { return 'auto' === $f['type']; } ) );
		$old    = get_transient( 'bc_old_' . get_current_user_id() );
		$old    = is_array( $old ) ? $old : array();
		delete_transient( 'bc_old_' . get_current_user_id() );
		?>
		<div class="wrap bc-wrap">
			<h1><?php esc_html_e( 'Créer un bon cadeau', 'bon-cadeau' ); ?></h1>
			<?php self::notice(); ?>

			<?php if ( ! BC_Template::image_path( $t ) ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( "Aucun visuel n'est configuré.", 'bon-cadeau' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG_TEMPLATE ) ); ?>"><?php esc_html_e( 'Configurer le modèle', 'bon-cadeau' ); ?></a>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bc-create-form">
				<?php wp_nonce_field( 'bc_generate' ); ?>
				<input type="hidden" name="action" value="bc_generate" />

				<div class="bc-panel">
					<h2><?php esc_html_e( 'Informations à remplir', 'bon-cadeau' ); ?></h2>
					<?php if ( ! $manual ) : ?>
						<p class="description"><?php esc_html_e( "Aucun champ manuel n'est utilisé dans le modèle.", 'bon-cadeau' ); ?></p>
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<?php foreach ( $manual as $key ) : ?>
							<?php self::field_row( $key, $fields[ $key ], $old[ $key ] ?? '' ); ?>
						<?php endforeach; ?>
					</table>
				</div>

				<div class="bc-panel">
					<h2><?php esc_html_e( 'Remplis automatiquement', 'bon-cadeau' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Laissez vide pour la valeur automatique, ou saisissez une valeur pour la forcer.', 'bon-cadeau' ); ?></p>
					<table class="form-table" role="presentation">
						<?php foreach ( $auto as $key ) : ?>
							<?php self::field_row( $key, $fields[ $key ], $old[ $key ] ?? '', true ); ?>
						<?php endforeach; ?>
					</table>
				</div>

				<div class="bc-panel">
					<h2><?php esc_html_e( 'Envoi', 'bon-cadeau' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="bc-email"><?php esc_html_e( 'Envoyer par e-mail à', 'bon-cadeau' ); ?></label></th>
							<td><input type="email" class="regular-text" id="bc-email" name="bc_email" value="<?php echo esc_attr( $old['bc_email'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Optionnel', 'bon-cadeau' ); ?>" /></td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Générer le bon cadeau', 'bon-cadeau' ), 'primary large' ); ?>
			</form>
		</div>
		<?php
	}

	private static function field_row( $key, array $field, $value, $auto = false ) {
		$id = 'bc-f-' . $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
			<td>
				<?php if ( 'textarea' === $field['input'] ) : ?>
					<textarea id="<?php echo esc_attr( $id ); ?>" name="bc[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
				<?php else : ?>
					<input type="<?php echo esc_attr( $field['input'] ); ?>" id="<?php echo esc_attr( $id ); ?>" name="bc[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" <?php echo $auto ? 'placeholder="' . esc_attr__( 'Automatique', 'bon-cadeau' ) . '"' : ''; ?> />
				<?php endif; ?>
				<code class="bc-key">{<?php echo esc_html( $key ); ?>}</code>
			</td>
		</tr>
		<?php
	}

	public static function handle_generate() {
		self::check( 'bc_generate' );
		$input = isset( $_POST['bc'] ) && is_array( $_POST['bc'] ) ? wp_unslash( $_POST['bc'] ) : array(); // phpcs:ignore -- sanitized in create().
		$email = isset( $_POST['bc_email'] ) ? sanitize_email( wp_unslash( $_POST['bc_email'] ) ) : '';

		$post_id = BC_Vouchers::create( array_map( 'strval', array_filter( $input, 'is_scalar' ) ) );
		if ( is_wp_error( $post_id ) ) {
			self::error( $post_id->get_error_message() );
			set_transient( 'bc_old_' . get_current_user_id(), array_merge( $input, array( 'bc_email' => $email ) ), 300 );
			self::redirect( self::SLUG_CREATE );
		}

		$msg = 'created';
		if ( $email ) {
			$msg = BC_Vouchers::send_email( $post_id, $email ) ? 'mailed' : 'mailfailed';
		}
		self::redirect( self::SLUG_LIST, array( 'bc_msg' => $msg, 'bc_new' => $post_id ) );
	}

	/* ------------------------------------------------------------------ */
	/* Vouchers list                                                       */
	/* ------------------------------------------------------------------ */

	public static function page_list() {
		// phpcs:disable WordPress.Security.NonceVerification
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$new_id = absint( $_GET['bc_new'] ?? 0 );
		// phpcs:enable

		$args = array(
			'post_type'      => BC_Vouchers::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $search ) {
			$args['s'] = $search;
		}
		if ( in_array( $status, array( 'valid', 'used' ), true ) ) {
			$args['meta_query'] = array( // phpcs:ignore
				array(
					'key'   => '_bc_status',
					'value' => $status,
				),
			);
		}
		$query = new WP_Query( $args );
		$base  = admin_url( 'admin.php?page=' . self::SLUG_LIST );
		?>
		<div class="wrap bc-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Bons cadeaux', 'bon-cadeau' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG_CREATE ) ); ?>" class="page-title-action"><?php esc_html_e( 'Créer un bon', 'bon-cadeau' ); ?></a>
			<hr class="wp-header-end" />
			<?php self::notice(); ?>

			<?php if ( $new_id && BC_Vouchers::POST_TYPE === get_post_type( $new_id ) ) : ?>
				<div class="bc-panel bc-new">
					<img src="<?php echo esc_url( self::action_url( 'bc_download', $new_id, array( 'format' => 'png', 'inline' => 1 ) ) ); ?>" alt="" />
					<p>
						<a class="button button-primary" href="<?php echo esc_url( self::action_url( 'bc_download', $new_id, array( 'format' => 'pdf' ) ) ); ?>"><?php esc_html_e( 'Télécharger le PDF', 'bon-cadeau' ); ?></a>
						<a class="button" href="<?php echo esc_url( self::action_url( 'bc_download', $new_id, array( 'format' => 'print' ) ) ); ?>" target="_blank"><span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Imprimer', 'bon-cadeau' ); ?></a>
						<a class="button" href="<?php echo esc_url( self::action_url( 'bc_download', $new_id, array( 'format' => 'png' ) ) ); ?>"><?php esc_html_e( 'Télécharger le PNG', 'bon-cadeau' ); ?></a>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bc-inline-email">
						<?php wp_nonce_field( 'bc_email_' . $new_id ); ?>
						<input type="hidden" name="action" value="bc_email" />
						<input type="hidden" name="id" value="<?php echo esc_attr( $new_id ); ?>" />
						<input type="email" name="email" required value="<?php echo esc_attr( get_post_meta( $new_id, '_bc_email', true ) ); ?>" placeholder="email@exemple.fr" />
						<button class="button"><span class="dashicons dashicons-email"></span> <?php esc_html_e( 'Envoyer par e-mail (PDF joint)', 'bon-cadeau' ); ?></button>
					</form>
				</div>
			<?php endif; ?>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( $base ); ?>" class="<?php echo '' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Tous', 'bon-cadeau' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( add_query_arg( 'status', 'valid', $base ) ); ?>" class="<?php echo 'valid' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Valides', 'bon-cadeau' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( add_query_arg( 'status', 'used', $base ) ); ?>" class="<?php echo 'used' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Utilisés', 'bon-cadeau' ); ?></a></li>
			</ul>
			<form method="get" class="search-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG_LIST ); ?>" />
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Rechercher un code', 'bon-cadeau' ); ?>" />
					<?php submit_button( __( 'Rechercher', 'bon-cadeau' ), '', '', false ); ?>
				</p>
			</form>

			<table class="widefat striped bc-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Code', 'bon-cadeau' ); ?></th>
						<th><?php esc_html_e( 'N°', 'bon-cadeau' ); ?></th>
						<th><?php esc_html_e( 'Valeur', 'bon-cadeau' ); ?></th>
						<th><?php esc_html_e( 'Bénéficiaire', 'bon-cadeau' ); ?></th>
						<th><?php esc_html_e( 'Émis le', 'bon-cadeau' ); ?></th>
						<th><?php esc_html_e( 'Expire le', 'bon-cadeau' ); ?></th>
						<th><?php esc_html_e( 'Statut', 'bon-cadeau' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bon-cadeau' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $query->have_posts() ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'Aucun bon cadeau pour le moment.', 'bon-cadeau' ); ?></td></tr>
				<?php endif; ?>
				<?php
				foreach ( $query->posts as $post ) :
					$raw     = (array) get_post_meta( $post->ID, '_bc_values', true );
					$display = BC_Vouchers::display_values( $raw );
					$st      = get_post_meta( $post->ID, '_bc_status', true );
					$expired = ! empty( $raw['date_expiration'] ) && $raw['date_expiration'] < current_time( 'Y-m-d' );
					$email   = get_post_meta( $post->ID, '_bc_email', true );
					?>
					<tr class="<?php echo $post->ID === $new_id ? 'bc-highlight' : ''; ?>">
						<td><strong><?php echo esc_html( $display['code'] ?? $post->post_title ); ?></strong></td>
						<td><?php echo esc_html( $display['numero'] ?? '' ); ?></td>
						<td><?php echo esc_html( $display['valeur'] ?? '' ); ?></td>
						<td><?php echo esc_html( $display['beneficiaire'] ?? '' ); ?><?php echo $email ? '<br><small>' . esc_html( $email ) . '</small>' : ''; ?></td>
						<td><?php echo esc_html( $display['date_emission'] ?? '' ); ?></td>
						<td><?php echo esc_html( $display['date_expiration'] ?? '' ); ?></td>
						<td>
							<?php if ( 'used' === $st ) : ?>
								<span class="bc-badge bc-used"><?php esc_html_e( 'Utilisé', 'bon-cadeau' ); ?></span>
							<?php elseif ( $expired ) : ?>
								<span class="bc-badge bc-expired"><?php esc_html_e( 'Expiré', 'bon-cadeau' ); ?></span>
							<?php else : ?>
								<span class="bc-badge bc-valid"><?php esc_html_e( 'Valide', 'bon-cadeau' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="bc-actions">
							<a href="<?php echo esc_url( self::action_url( 'bc_download', $post->ID, array( 'format' => 'png', 'inline' => 1 ) ) ); ?>" target="_blank"><?php esc_html_e( 'Voir', 'bon-cadeau' ); ?></a> ·
							<a href="<?php echo esc_url( self::action_url( 'bc_download', $post->ID, array( 'format' => 'pdf' ) ) ); ?>">PDF</a> ·
							<a href="<?php echo esc_url( self::action_url( 'bc_download', $post->ID, array( 'format' => 'png' ) ) ); ?>">PNG</a> ·
							<a href="<?php echo esc_url( self::action_url( 'bc_download', $post->ID, array( 'format' => 'print' ) ) ); ?>" target="_blank"><?php esc_html_e( 'Imprimer', 'bon-cadeau' ); ?></a> ·
							<?php if ( 'used' === $st ) : ?>
								<a href="<?php echo esc_url( self::action_url( 'bc_status', $post->ID, array( 'status' => 'valid' ) ) ); ?>"><?php esc_html_e( 'Remettre valide', 'bon-cadeau' ); ?></a> ·
							<?php else : ?>
								<a href="<?php echo esc_url( self::action_url( 'bc_status', $post->ID, array( 'status' => 'used' ) ) ); ?>"><?php esc_html_e( 'Marquer utilisé', 'bon-cadeau' ); ?></a> ·
							<?php endif; ?>
							<a href="#" class="bc-email-toggle"><?php esc_html_e( 'E-mail', 'bon-cadeau' ); ?></a> ·
							<a href="<?php echo esc_url( self::action_url( 'bc_delete', $post->ID ) ); ?>" class="bc-delete" onclick="return confirm('<?php echo esc_js( __( 'Supprimer définitivement ce bon ?', 'bon-cadeau' ) ); ?>');"><?php esc_html_e( 'Supprimer', 'bon-cadeau' ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bc-email-form" hidden>
								<?php wp_nonce_field( 'bc_email_' . $post->ID ); ?>
								<input type="hidden" name="action" value="bc_email" />
								<input type="hidden" name="id" value="<?php echo esc_attr( $post->ID ); ?>" />
								<input type="email" name="email" required value="<?php echo esc_attr( $email ); ?>" placeholder="email@exemple.fr" />
								<button class="button button-small"><?php esc_html_e( 'Envoyer', 'bon-cadeau' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			if ( $query->max_num_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $query->max_num_pages,
						)
					)
				);
				echo '</div></div>';
			}
			?>
			<script>
			document.querySelectorAll('.bc-email-toggle').forEach(function (a) {
				a.addEventListener('click', function (e) {
					e.preventDefault();
					var f = a.parentNode.querySelector('.bc-email-form');
					f.hidden = !f.hidden;
				});
			});
			</script>
		</div>
		<?php
	}

	private static function voucher_from_request( $action ) {
		if ( ! current_user_can( bc_capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'bon-cadeau' ), 403 );
		}
		$id = absint( $_REQUEST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( $action . '_' . $id );
		if ( BC_Vouchers::POST_TYPE !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Bon introuvable.', 'bon-cadeau' ), 404 );
		}
		return $id;
	}

	public static function handle_download() {
		$id     = self::voucher_from_request( 'bc_download' );
		$path   = BC_Vouchers::file_path( $id );
		$format = sanitize_key( $_GET['format'] ?? '' ); // phpcs:ignore
		if ( ! $path ) {
			wp_die( esc_html__( 'Fichier introuvable.', 'bon-cadeau' ), 404 );
		}
		$name = 'bon-cadeau-' . sanitize_file_name( get_post_meta( $id, '_bc_code', true ) );
		nocache_headers();
		if ( 'print' === $format ) {
			$src = self::action_url( 'bc_download', $id, array( 'format' => 'png', 'inline' => 1 ) );
			header( 'Content-Type: text/html; charset=utf-8' );
			?>
<!doctype html>
<html><head><meta charset="utf-8"><title><?php echo esc_html( $name ); ?></title>
<style>
@page { margin: 0; }
html, body { margin: 0; padding: 0; background: #fff; }
img { display: block; max-width: 100%; max-height: 100vh; margin: 0 auto; }
@media print { img { width: 100%; height: auto; max-height: none; page-break-inside: avoid; } }
</style></head>
<body><img src="<?php echo esc_url( $src ); ?>" alt="" onload="setTimeout(function(){window.print();},200)"></body></html>
			<?php
			exit;
		}
		if ( 'pdf' === $format ) {
			$pdf = BC_Renderer::pdf_from_png( $path );
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: ' . ( empty( $_GET['inline'] ) ? 'attachment' : 'inline' ) . '; filename="' . $name . '.pdf"' ); // phpcs:ignore
			header( 'Content-Length: ' . strlen( $pdf ) );
			echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
		$disposition = empty( $_GET['inline'] ) ? 'attachment' : 'inline'; // phpcs:ignore
		header( 'Content-Type: image/png' );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . $name . '.png"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore
		exit;
	}

	public static function handle_status() {
		$id = self::voucher_from_request( 'bc_status' );
		BC_Vouchers::set_status( $id, sanitize_key( $_GET['status'] ?? '' ) ); // phpcs:ignore
		self::redirect( self::SLUG_LIST, array( 'bc_msg' => 'status' ) );
	}

	public static function handle_delete() {
		$id = self::voucher_from_request( 'bc_delete' );
		wp_delete_post( $id, true );
		self::redirect( self::SLUG_LIST, array( 'bc_msg' => 'deleted' ) );
	}

	public static function handle_email() {
		$id    = self::voucher_from_request( 'bc_email' );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ); // phpcs:ignore
		$ok    = BC_Vouchers::send_email( $id, $email );
		self::redirect( self::SLUG_LIST, array( 'bc_msg' => $ok ? 'mailed' : 'mailfailed' ) );
	}
}

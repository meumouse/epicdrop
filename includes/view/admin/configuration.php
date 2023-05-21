<?php

/**
 * Functions response will display in settings EpicDrop in admin panel
 *
 * @package: epicdrop
 * @since 1.0.0
 *
 */
 
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (!function_exists('epicdrop_settings_callback')) {
	function epicdrop_settings_callback() { ?>
		<style>
		.form-group {
			margin-bottom: 15px;
		}

		.form-group *{
			-webkit-box-sizing: border-box;
			box-sizing: border-box;
		}

		.input-group {
			border-collapse: separate;
			display: table;
			position: relative;
			width: 80%;
		}

		.input-group input[type=text], .input-group textarea {
			float: left;
			margin-bottom: 0;
			position: relative;
			width: 100%;
			z-index: 2;
			display: table-cell;
			border: 1px solid #c7d6db;
			border-radius: .35rem;
			color: #212529;
			font-size: 1em;
			height: 40px;
			line-height: 1.42857;
			padding: .5rem .75rem;
			border-bottom-right-radius: 0;
			border-top-right-radius: 0;
			background: #fff;
			transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out, -webkit-box-shadow .15s ease-in-out;
		}

		.input-group input[type=text]:focus, .input-group textarea:focus {
			border-color: #5A189A;
			box-shadow: none;
		}

		.input-group textarea {
			height: 100px;
			border-radius: .35rem;
		}

		.input-group-addon {
			vertical-align: middle;
			white-space: nowrap;
			width: 3%;
			background-color: #fff;
			border: 1px solid #c7d6db;
			border-radius: .35rem;
			color: #5A189A;
			font-size: 1em;
			font-weight: 400;
			line-height: 1;
			padding: .5rem .75rem;
			text-align: center;
			border-bottom-left-radius: 0;
			border-top-left-radius: 0;
			cursor: pointer;
			display: table-cell;
			border-left: none;
			transition: color 0.2s ease-in-out, background-color 0.2s ease-in-out, border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
		}

		.input-group-addon:hover {
			color: #fff;
			background-color: #5A189A;
		}

		input#affiliate_id {
			border-radius: .35rem;
		}
		
		#nxtalimporter-toggle-content {
			cursor: pointer;
		}

		.ellipsis {
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			cursor: pointer;
		}

		.hide {
			display:none !important;
		}

		.log-content {
			background: #fff;
			padding: 0.5rem;
			margin-top: 1rem;
			border: 1px solid #c7d6db;
			max-height: 1000px;
			overflow: auto;
			min-height: 100px;
			color: #444;
			border-radius: 0.35rem;
		}

		p.form-text {
			margin-top: 0.25rem;
			margin-left: .5rem;
			font-size: .875em;
			color: #6c757d;
		}

		p.description {
			width: 80%;
		}

		#nxt-generateHashKey, #log-show, #hide-logs, #clear-logs {
			display: inline-block;
			font-weight: 400;
			line-height: 1.5;
			color: #5A189A;
			text-align: center;
			text-decoration: none;
			vertical-align: middle;
			cursor: pointer;
			-webkit-user-select: none;
			-moz-user-select: none;
			user-select: none;
			background-color: transparent;
			border: 1px solid #5A189A;
			padding: 0.375rem 0.75rem;
			margin-bottom: 1rem;
			font-size: 1em;
			border-radius: .35rem;
			box-shadow: none;
			transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
		}

		#nxt-generateHashKey:hover, #log-show:hover, #hide-logs:hover, #clear-logs:hover {
			color: #fff;
			background-color: #5A189A;
		}

		input#advance_option, input#log {
			width: 3em;
			height: 1.5em;
			background-color: #fff;
			background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%280, 0, 0, 0.25%29'/%3e%3c/svg%3e");
			background-position: left center;
			background-size: contain;
			background-repeat: no-repeat;
			border-radius: 2em;
			border: 1px solid rgba(0,0,0,.25);
			appearance: none;
   			-webkit-print-color-adjust: exact;
			box-shadow: none;
			transition: background-position .15s ease-in-out;
		}

		input#advance_option:checked[type=checkbox], input#log:checked[type=checkbox] {
			background-position: right center;
			background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
		}

		input#advance_option[type=checkbox]:checked::before, input#log[type=checkbox]:checked::before {
			content: none;
		}

		input#advance_option:checked, input#log:checked {
			background-color: #5A189A;
			border-color: #5A189A;
		}

		input#advance_option:focus, input#log:focus {
			border-color: #5A189A;
			outline: none;
			box-shadow: none;
		}

		input#submit {
			display: inline-block;
			font-weight: 400;
			line-height: 1.5;
			color: #fff;
			text-align: center;
			text-decoration: none;
			vertical-align: middle;
			cursor: pointer;
			-webkit-user-select: none;
			-moz-user-select: none;
			user-select: none;
			background-color: #5A189A;
			border: 1px solid #5A189A;
			padding: 0.375rem 0.75rem;
			font-size: 1rem;
			border-radius: .35rem;
			transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
		}

		input#submit:hover {
			background-color: #3C096C;
			border-color: #3C096C;
			transform: translate(0px, 0px) !important;
		}

		input#submit:focus {
			outline: none;
			box-shadow: none;
		}

		div.wrap table.form-table {
			width: 85%;
		}
		</style>

		<div class="wrap">
			<h1><?php echo esc_html(__('Configurar EpicDrop', 'epicdrop')); ?></h1>
			<?php if (isset($_GET['update'])) { ?>
			<div class="notice notice-success settings-error is-dismissible"> 
				<p><strong><?php echo esc_html(__('As configurações foram atualizadas com sucesso.', 'epicdrop')); ?></strong></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php echo esc_html(__('Descartar essa notificação.', 'epicdrop')); ?></span>
				</button>
			</div>	
				<?php 
			}

			if (isset($_GET['error'])) { 
				?>
			<div class="notice notice-error settings-error is-dismissible"> 
				<p><strong><?php echo esc_html(__('Chave secreta da API inválida.', 'epicdrop')); ?></strong></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php echo esc_html(__('Descartar essa notificação.', 'epicdrop')); ?></span>
				</button>
			</div>	
				<?php 
			}

			if (isset($_GET['clear'])) { 
				?>
			<div class="notice notice-success settings-error is-dismissible"> 
				<p><strong><?php echo esc_html(__('Registros limpos com sucesso.', 'epicdrop')); ?></strong></p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php echo esc_html(__('Descartar essa notificação.', 'epicdrop')); ?></span>
				</button>
			</div>	
			<?php } ?>

			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="epicdrop_importer_save_configuration">

				<?php wp_nonce_field('epicdrop_importer_fields_verify'); ?>
				<?php $configuration = get_option('importer_setting'); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="import_link"><?php echo esc_html(__('URL de conexão', 'epicdrop')); ?></label>
							</th>
							<td>
								<div class="form-group">
									<div class="input-group">
										<?php $importLink = get_rest_url(null, 'v1/epicdrop'); ?>
										<input type="text" id="import_link" value="<?php echo esc_html( $importLink ); ?>" readonly="readonly" >
										<span class="input-group-addon" id="copy_btn" onClick="copyToClipboard('import_link');"><?php echo esc_html(__('Copiar', 'epicdrop')); ?></span>
									</div>
									<p class="description form-text"><?php echo sprintf( esc_html__( 'Use esta URL se para conectar com a extensão no Chrome. %s', 'epicdrop' ), '<a href="https://chrome.google.com/webstore/" target="_blank">' . esc_html__( 'Baixar extensão para o Google Chrome', 'epicdrop' ) . '</a>' ); ?></p>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="secret_key"><?php echo esc_html(__('Chave secreta da API', 'epicdrop')); ?></label>
							</th>
							<td>
								<?php 
								$secret_key = '';
								if (isset($configuration['secret_key'])) {
									$secret_key = $configuration['secret_key'];									
								} 
								?>
								<div class="form-group">
									<div class="input-group">
										<input type="text" id="secret_key" name="secret_key" value="<?php echo esc_html($secret_key); ?>" required>
										<span class="input-group-addon" id="copy_btn_secret_key" onClick="copyToClipboard('secret_key');"><?php echo esc_html(__('Copiar', 'epicdrop')); ?></span>
									</div>
									<p class="description form-text"><?php echo esc_html(__('A chave secreta pode ser qualquer string aleatória com pelo menos 8 caracteres.', 'epicdrop')); ?></p>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>&nbsp;</label>
							</th>
							<td>
								<input type="button" onclick="return changeKey();" id="nxt-generateHashKey" class="button button-default" value="<?php echo esc_html(__('Gerar nova chave secreta', 'epicdrop')); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="advance_option">
									<?php echo esc_html(__('Opções avançadas', 'epicdrop')); ?>
								</label>
							</th>
							<td>
								<p class="description form-text">
								<input name="advance_option" type="checkbox" id="advance_option" value="1"
								<?php 
								if (isset($configuration['advance_option']) && $configuration['advance_option']) {
									?>
									 checked <?php } ?>>
								<?php echo esc_html(__('Ative a opção avançada para ter mais opções de controle para importar seus produtos. Depois de alterar esta opção, você precisará atualizar as configurações da extensão do Chrome.', 'epicdrop')); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="affiliate_id">
									<?php echo esc_html(__('ID de afiliado', 'epicdrop')); ?>
								</label>
							</th>
							<td>
									<div class="input-group">
									<?php 
										$affiliate_id = '';
									if (isset($configuration['affiliate_id'])) {
										$affiliate_id = $configuration['affiliate_id'];								
									} 
									?>
									<input type="text" id="affiliate_id" name="affiliate_id" value="<?php echo esc_html($affiliate_id); ?>">
									</div>
									<p class="description form-text"><?php echo esc_html(__('Seus parâmetros de URL de afiliado (por exemplo: tag=epicdrop). Pode ser alterado ao importar o produto.', 'epicdrop')); ?></p>
							
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="replace_texts">
									<?php echo esc_html(__('Substituir textos', 'epicdrop')); ?>
								</label>
							</th>
							<td>
								<div class="input-group">
								<?php 
									$replace_texts = '';
								if (isset($configuration['replace_texts'])) {
									$replace_texts = $configuration['replace_texts'];								
								} 
								?>
								<textarea id="replace_texts" name="replace_texts" placeholder="<?php echo esc_html(__('Encontrar:Substituir', 'epicdrop')); ?>"><?php echo esc_html($replace_texts); ?></textarea>
								</div>
								<p class="description form-text"><?php echo esc_html(__('Adicione o texto que deseja substituir com as informações do produto importado (por exemplo: Encontrar:Substituir, China:Internacional). Você pode adicionar vários textos separados por vírgulas.', 'epicdrop')); ?></p>
							
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="log"><?php echo esc_html(__('Registros', 'epicdrop')); ?></label>
							</th>
							<td>					
									<p class="description form-text">
										<input name="log" type="checkbox" id="log" value="1"
										<?php if (isset($configuration['log']) && $configuration['log']) { ?>
										 checked <?php } ?>>
										<?php echo esc_html(__('Ativar registros.', 'epicdrop')); ?>
									</p>
								
							</td>
						</tr>
						<?php if (isset($configuration['log']) && $configuration['log'] && file_exists(EPICDROP_DIR . '/debug.log')) { ?>
						<tr>
							<th scope="row">
								<label>&nbsp;</label>
							</th>
							<td>
								<input type="button" onclick="return toggleSection('log-section', 'log-show');" id="log-show" class="button button-default" value="<?php echo esc_html(__('Mostrar registros', 'epicdrop')); ?>">
								<div id="log-section" class="hide">
									<input type="button" onclick="return toggleSection('log-show', 'log-section');" class="button button-default" id="hide-logs" value="<?php echo esc_html(__('Ocultar registros', 'epicdrop')); ?>">
									<a href="<?php echo esc_html( admin_url('edit.php?post_type=product&page=epicdrop_settings&clear_log=1') ); ?>" class="button button-default" id="clear-logs"><?php echo esc_html(__('Limpar registros', 'epicdrop')); ?></a>
									<div class="log-content">
										<pre><?php echo esc_html( file_get_contents(EPICDROP_DIR . '/debug.log') ); ?></pre>
									</div>
								</div>
							</td>
						</tr>
						<?php } ?>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_html( __('Salvar alterações', 'epicdrop') ); ?>">
				</p>

				<script>
					function randomHashKey(length) {
						var chars = "abcdefghijklmnopqrstuvwxyz!@ABCDEFGHIJKLMNOP1234567890";
						var hashkey = "";
						for (var x = 0; x < length; x++) {
							var i = Math.floor(Math.random() * chars.length);
							hashkey += chars.charAt(i);
						}
						return hashkey;
					}

					function changeKey() {
						document.getElementById('secret_key').value = randomHashKey(55);
					}

					function toggleSection(show, hide) {
						document.getElementById(show).classList.remove('hide');
						document.getElementById(hide).classList.add('hide');
					}
					
					function copyToClipboard(element) {
						const btn = document.getElementById('copy_btn');
						btn.addEventListener('click', function() {
						btn.textContent = 'Copiado!';
						});

						const btn_secret_key = document.getElementById('copy_btn_secret_key');
						btn_secret_key.addEventListener('click', function() {
						btn_secret_key.textContent = 'Copiado!';
						});

						var copyText = document.getElementById(element);
						copyText.select();
						copyText.setSelectionRange(0, 99999);
						document.execCommand("copy");
					}
				</script>

			</form>
		</div>
		<?php
	}
}
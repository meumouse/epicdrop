<?php
/**
 * The generated form will be used in Chrome extension to import the product accordinglly.
 *
 * @package: epicdrop
 * @since 1.0.0
 */
 
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (!function_exists('EpicDrop_Import_form')) {
	function EpicDrop_Import_form( $data) {
		$html = null;

		if ($data['options']) {
			$html = '<h5 class="card-title text-center">' . __('Opções de importação', 'epicdrop') . '</h5>
			<h6 class="text-center">' . __('Selecione as opções que deseja importar.', 'epicdrop') . '</h6>
			<div class="form-group">
				<input type="text" name="affiliate_link" value="' . $data['affiliateLink'] . '" placeholder="' . __('Link de afiliado', 'epicdrop') . '" class="input">
				<p class="help-block">' . __('Insira o link de afiliado do produto.', 'epicdrop') . '</p>
			</div>
			<div class="form-group">
				<input type="text" name="id_product" placeholder="' . __('ID do produto existente', 'epicdrop') . '" class="input">
				<p class="help-block">' . __('Se você deseja atualizar algum produto existente com os dados dessas opções, insira o ID do produto, caso contrário, um novo produto será criado em sua loja.', 'epicdrop') . '</p>
			</div>';
			foreach ($data['options'] as $option) {
				$html .= '<div class="form-group">
					<label>
						<input name="' . $option['name'] . '" type="checkbox" checked="checked" value="1"> ' . $option['label'];
				if ('' != $option['desc']) {
					$html .= '<span class="help-block"> (' . $option['desc'] . ')</span>';
				}
				$html .= '</label>
				</div>';
			}
			if ($data['isAdvaceEnabled']) {
				$html .= '<div class="form-group text-right">
					<a href="#" class="toggle-more">' . __('Opções avançadas', 'epicdrop') . '</a>
				</div>
				<div class="toggle-content row">
					<div class="form-group row">
						<label for="association_sku" class="col-4 col-form-label">' . __('SKU', 'epicdrop') . '</label>
						<div class="col-8">
							<input type="text" class="form-control" id="association_sku" name="association[sku]" value="">
							<span class="help-block">' . __('Defina o SKU do produto.', 'epicdrop') . '</span>		
						</div>
					</div>				
					<div class="form-group row">
						<label for="association_categories" class="col-4 col-form-label">' . __('Atribuir categorias', 'epicdrop') . '</label>
						<div class="col-8">
							<select name="association[categories][]" id="association_categories" class="form-control chosen" multiple="true">';
				foreach ($data['categories'] as $category) {
					$html .= '<option value="' . __($category['term_id']) . '">' . $category['name'] . '</option>';
				}
				$html .= '</select>		
							<span class="help-block">' . __('Escolha categorias para atribuir ao produto.', 'epicdrop') . '</span>
						</div>		
					</div>
					<div class="form-group row">
						<label for="association_tax_class" class="col-4 col-form-label">' . __('Classe de imposto', 'epicdrop') . '</label>
						<div class="col-8">
							<select class="form-control" id="association_tax_class" name="association[tax_class]">
							<option value="0">' . __('Padrão', 'epicdrop') . '</option>';
				foreach ($data['taxClasses'] as $taxClass) {
					$html .= '<option value="' . $taxClass['slug'] . '">' . $taxClass['name'] . '</option>';
				}
				$html .= '</select>
							<span class="help-block">' . __('Definir classe de imposto do produto.', 'epicdrop') . '</span>	
						</div>		
					</div>
					<div class="form-group row">
						<label for="association_quantity" class="col-4 col-form-label">' . __('Quantidade', 'epicdrop') . '</label>
						<div class="col-8">
							<input type="text" class="form-control" id="association_quantity" name="association[quantity]" value="">
							<span class="help-block">' . __('Definir quantidade de estoque do produto.', 'epicdrop') . '</span>		
						</div>
					</div>
					<div class="form-group row">
						<label for="association_price" class="col-4 col-form-label">' . __('Preço', 'epicdrop') . '</label>
						<div class="col-8">
							<input type="text" class="form-control" id="association_price" name="association[price]" value="">
							<span class="help-block">' . __('Set product price. You can either set a price prefix with (+,-,*,/) to calculate with the current price or set a fixed price value to override the current one.', 'epicdrop') . '</span>		
						</div>
					</div>					
					<div class="form-group row">
						<label for="association_visibility" class="col-4 col-form-label">' . __('Visibilidade', 'epicdrop') . '</label>
						<div class="col-8">
							<select class="form-control" id="association_visibility" name="association[visibility]">
								<option value="visible">' . __('Loja e resultados de pesquisa', 'epicdrop') . '</option>
								<option value="catalog">' . __('Apenas loja', 'epicdrop') . '</option>
								<option value="search">' . __('Apenas resultados de pesquisa', 'epicdrop') . '</option>
								<option value="hidden">' . __('Oculto', 'epicdrop') . '</option>
							</select>
							<span class="help-block">' . __('Definir visibilidade do catálogo.', 'epicdrop') . '</span>	
						</div>		
					</div>
					<div class="form-group row">
						<label for="association_update_existing" class="col-4 col-form-label">' . __('Atualizar existente', 'epicdrop') . '</label>
						<div class="col-8">
							<select class="form-control" id="association_update_existing" name="association[update_existing]">
								<option value="0">' . __('Não atualizar', 'epicdrop') . '</option>
								<option value="1">' . __('Atualize com esses dados', 'epicdrop') . '</option>
							</select>		
							<span class="help-block">' . __('Aplicável somente se o ID do produto estiver vazio.', 'epicdrop') . '</span>	
						</div>		
					</div>
					<div class="form-group row">
						<label for="association_review" class="col-4 col-form-label">' . __('Máximo de avaliações', 'epicdrop') . '</label>
						<div class="col-8">
							<input type="text" class="form-control" id="association_review" name="association[review]" value="10">
							<span class="help-block">' . __('Set maximum product reviews to import. Set 0 for all reviews, this may increase the import execution time. Applicable only if Customer Reviews option is checked.', 'epicdrop') . '</span>		
						</div>
					</div>
					<div class="form-group row">
						<label for="association_post_status" class="col-4 col-form-label">' . __('Status', 'epicdrop') . '</label>
						<div class="col-8">
							<select class="form-control" id="association_post_status" name="association[post_status]">
								<option value="draft">' . __('Rascunho', 'epicdrop') . '</option>
								<option value="pending">' . __('Revisão pendente', 'epicdrop') . '</option>
								<option value="publish">' . __('Publicado', 'epicdrop') . '</option>
							</select>		
							<span class="help-block">' . __('Defina o status do produto.', 'epicdrop') . '</span>	
						</div>		
					</div>
				</div>';
			}
		}
		return $html;
	}
}
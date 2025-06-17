<?php

namespace Drupal\deims_icos_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Plugin implementation of the 'DeimsIcosFormatter' formatter.
 *
 * @FieldFormatter(
 *   id = "deims_icos_formatter",
 *   label = @Translation("DEIMS ICOS Formatter"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "string",
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
 
class DeimsIcosFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
   
 
	public function settingsSummary() {
		$summary = [];
		$summary[] = $this->t('Formats a deims.id field of Drupal');
		return $summary;
	}

  /**
   * {@inheritdoc}
   */
	public function viewElements(FieldItemListInterface $items, $langcode) {
		$elements = [];
		// Render each element as markup in case of multi-values.

		foreach ($items as $delta => $item) {
			
			$deims_id = $item->value;
			// get affiliation field of node via uuid
			// Load the node entity by UUID.
			$nodes = \Drupal::entityTypeManager()
			  ->getStorage('node')
			  ->loadByProperties(['uuid' => $deims_id]);

			// Since loadByProperties returns an array of entities, get the first one.
			$node = reset($nodes);
			$field_affiliation = $node->get('field_affiliation');
			$icos_station_code = '';
		
			// iterate through affiliation field and query ICOS station code
			foreach ($field_affiliation as $index => $field_affiliation_item) {
				
				if ($field_affiliation_item->entity instanceof Paragraph) {
					
					$paragraph = $field_affiliation_item->entity;
					
					if (!$paragraph->get('field_network')->isEmpty()) {

						$network_nid = $paragraph->get('field_network')->target_id;
						if ($network_nid == 12825) {
							$icos_station_code_string = $paragraph->get('field_network_specific_site_code')->value;
							// normalise ICOS station code
							$icos_station_code = basename($icos_station_code_string);
							// \Drupal::logger('deims_icos_formatter')->info($icos_station_code);

						}
						
					}
				}
			}					
			
			if ($icos_station_code == '') {
				return array();
			}
			
			// use station code to query ICOS portal - see sparql query
			$query_string = file_get_contents(__DIR__ . '/icos.sparql');
			$query_string = str_replace('{{replace-me}}', $icos_station_code, $query_string);
			
			$output = "";

			$api_url = "https://meta.icos-cp.eu/sparql";
			$base_url = "https://data.icos-cp.eu/portal/#";
			$appendix = urlencode('{"filterCategories":{"station":["i'.$icos_station_code.'"]}}');
			$landing_page_url = $base_url . $appendix;
			// \Drupal::logger('deims_icos_formatter')->info($landing_page_url);
			
			try {
				$response = \Drupal::httpClient()->post($api_url, [
					'headers' => [
						'Accept' => 'application/sparql-results+json',
						'Content-Type' => 'application/sparql-query',
					],
					'body' => $query_string,
				]);
				$data = (string) $response->getBody();
				
				if (empty($data)) {
					// potentially add a more meaningful error message here in case data can't be fetched from ICOS
					\Drupal::logger('deims_icos_formatter')->notice(serialize(array()));
				}
				else {
					$output = "There is data for this site in the ICOS Data Portal. Click here to <a href='$landing_page_url'>visit the ICOS Data Portal.</a>";
                }
			}
			catch (GuzzleException $e) {
				if ($e->hasResponse()) {
                    $response = $e->getResponse()->getBody()->getContents();
					\Drupal::logger('deims_icos_formatter')->notice(serialize($response));
                }
				return array();
			}
			
			$elements[$delta] = [
				'#markup' => $output,
			];

		}

		return $elements;

	}
	
}

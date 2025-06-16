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
							\Drupal::logger('deims_icos_formatter')->info($icos_station_code);

						}
						
					}
				}
			}					
			
			if ($icos_station_code == '') {
				return array();
			}
			
			// use station code to query ICOS portal - see sparql query
			// render query result
			require_once __DIR__ . '/query.php';
			\Drupal::logger('deims_icos_formatter')->info($query_string);
			
			$output = "";
			
			/*
		  
			$api_url = "https://dar.elter-ri.eu/api/search/?q=&sort=newest&page=1&size=10&metadata_siteReferences_siteID=" . $item->value;
			$landing_page_url = "https://dar.elter-ri.eu/search/?q=&l=list&p=1&s=10&sort=newest&f=metadata_siteReferences_siteID:" . $item->value;
			
			try {
				$response = \Drupal::httpClient()->get($api_url, array('headers' => array('Accept' => 'application/json')));
				$data = (string) $response->getBody();
				if (empty($data)) {
					// potentially add a more meaningful error message here in case data can't be fetched from ICOS
					\Drupal::logger('deims_icos_formatter')->notice(serialize(array()));
				}
				else {
					$data = json_decode($data, TRUE);
                }
			}
			catch (GuzzleException $e) {
				if ($e->hasResponse()) {
                    $response = $e->getResponse()->getBody()->getContents();
					\Drupal::logger('deims_icos_formatter')->notice(serialize($response));
                }
				return array();
			}
			
			if (intval($data["hits"]["total"])>0) {
				
				$maxIterations = 5;
				$count = 0;
				$dataset_list = "<ul>";

				foreach ($data["hits"]["hits"] as $key => $value) {
					if ($count >= $maxIterations) {
						break;
					}
					$count++;
				
					$url = htmlspecialchars($value["links"]["self_html"] ?? '#', ENT_QUOTES, 'UTF-8');
					$title = htmlspecialchars($value["metadata"]["titles"][0]["titleText"] ?? 'Untitled', ENT_QUOTES, 'UTF-8');
					$dataset_list .= "<li><a href='$url'>$title</a></li>";
					
				}
				
				$dataset_list .= "</ul>";
				
				if ($data["hits"]["total"] == 1) {
					$output = "There is one dataset for this site available in ICOS:";
				}
				else {
					$output = "There is a total of " . $data["hits"]["total"] . " datasets for this site available in ICOS.";
				}
				
				if ($count>0) {
					if ($data["hits"]["total"]>5) {
						$output .= " The latest ones include:";
					} 
					$output .= $dataset_list;
				}
				$output .= "To see more <a href='$landing_page_url'>visit the ICOS Portal.</a>";

			}
			else {
				// need to return empty array for Drupal to realise the field is empty without throwing an error
				return array();
			}
			
			*/
			
			$elements[$delta] = [
				'#markup' => $output,
			];
			
			

		}

		return $elements;

	}
	
}

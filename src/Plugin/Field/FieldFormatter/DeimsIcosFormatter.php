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
			$list_of_icos_station_codes = [];
		
			// iterate through affiliation field and query ICOS station code
			foreach ($field_affiliation as $index => $field_affiliation_item) {
				
				if ($field_affiliation_item->entity instanceof Paragraph) {
					
					$paragraph = $field_affiliation_item->entity;
					
					if (!$paragraph->get('field_network')->isEmpty()) {

						$network_nid = $paragraph->get('field_network')->target_id;
						if ($network_nid == 12825) {
							$icos_station_code_string = $paragraph->get('field_network_specific_site_code')->value;
							// check if there are multiple ICOS station codes
							if (strpos($icos_station_code_string, ',') !== false) {
								// Split the string at each comma
								$list_of_icos_station_codes = explode(',', $icos_station_code_string);
							}
							else {
								$list_of_icos_station_codes = [$icos_station_code_string];
							}
						}
					}
				}
			}					
			
			if (empty($list_of_icos_station_codes)) {
				return array();
			}
			
			$sparql_icos_station_string = "";
			// normalise ICOS station code
			foreach ($list_of_icos_station_codes as &$value) {
				$value = trim($value);
				$value = basename($value);
				$sparql_icos_station_string .= "<http://meta.icos-cp.eu/resources/stations/$value> ";
			}

			// use station code to query ICOS portal - see sparql query
			// <http://meta.icos-cp.eu/resources/stations/ES_FI-Ken> <http://meta.icos-cp.eu/resources/stations/AZR>
			
			$query_string = file_get_contents(__DIR__ . '/icos.sparql');
			$query_string = str_replace('{{replace-me}}', $sparql_icos_station_string, $query_string);
			
			$output = "";

			$api_url = "https://meta.icos-cp.eu/sparql";
			$base_url = "https://data.icos-cp.eu/portal/#";
			
			$formatted_icos_stations_string = '';

			//example for filtering by two stations: 
			// https://data.icos-cp.eu/portal/#{"filterCategories":{"station":["iES_FI-Ken","iAZR"]}}
			// Use a for loop to wrap each string with quotes and prefix with 'i'
			foreach ($list_of_icos_station_codes as $current_code) {
				$formatted_icos_stations_string .= '"i' . $current_code . '",'; // Append each modified element to the result string
			}
			// remove the last comma
			$formatted_icos_stations_string = substr($formatted_icos_stations_string, 0, -1);
			
			$appendix = urlencode('{"filterCategories":{"station":[' . $formatted_icos_stations_string . ']}}');
			$landing_page_url = $base_url . $appendix;
			
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
					\Drupal::logger('deims_icos_formatter')->notice('No data returned from ICOS SPARQL API for station code: ' . implode(', ', $list_of_icos_station_codes));
				}
				else {
					
					$results_object = json_decode($response->getBody(), true);
					\Drupal::logger('deims_icos_formatter')->notice('Log: ' . $response->getBody());
					$dataset_list = "<ul>";
					
					foreach ($results_object["results"]["bindings"] as $dataset) {
						$title = $dataset["datasetTitle"]["value"];
						$url = $dataset["url"]["value"];
						$dataset_list .= "<li><a href='$url'>$title</a></li>";
					}
					
					$dataset_list .= "</ul>";
					$output = "Data for this site is available through the ICOS Data Portal. The most recent datasets include:<br>" . $dataset_list;
					$output .= "You can <a href='$landing_page_url'>click here to access the data in the ICOS Data Portal</a>.";
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

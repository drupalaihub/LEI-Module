<?php

namespace Drupal\lei\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Datetime\DrupalDateTime;

class LEIController extends ControllerBase {

  /**
   * Fetches and updates GLEIF data for a specific application.
   *
   * @param int $nid
   *   The node ID of the application.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating success or failure.
   */
  public function updateGleifData($nid) {
    // Load the node.
    $node = \Drupal\node\Entity\Node::load($nid);

    if ($node && $node->bundle() === 'application') {
      // Get the registration number and country code from the node.
      $registration_number = $node->get('field_registration_number')->value;
      $country_code = $node->get('field_country_code')->value;

      // Construct the API request URL.
      $api_url = "https://api.gleif.org/api/v1/lei-records?filter[entity.jurisdiction]={$country_code}&filter[entity.registeredAs]={$registration_number}";

      try {
        // Perform the API request.
        $response = \Drupal::httpClient()->get($api_url, [
          'headers' => [
            'Accept' => 'application/json',
          ],
        ]);

        // Check for a successful response.
        if ($response->getStatusCode() === 200) {
          $data = json_decode($response->getBody(), TRUE);

          // Extract the relevant data from the API response.
          if (!empty($data['data']) && is_array($data['data'])) {
            $lei_data = $data['data'][0];
            $lei_code = $lei_data['id'];
            $next_renewal_date = isset($lei_data['attributes']['registration']['nextRenewalDate']) ? strtotime($lei_data['attributes']['registration']['nextRenewalDate']) : NULL;
            $lei_status = isset($lei_data['attributes']['registration']['status']) ? $lei_data['attributes']['registration']['status'] : NULL;

            // Update the node with the new data.
            $node->set('field_lei_code', $lei_code);

            // Ensure proper format for next renewal date.
            if ($next_renewal_date !== NULL) {
              $next_renewal_date_formatted = date('Y-m-d', $next_renewal_date);
              $node->set('field_next_renewal_date', $next_renewal_date_formatted);
            }

            $node->set('field_lei_status', $lei_status);
            
            // Update the "GLEIF last update" field with the current time.
            $current_time = new DrupalDateTime();
            $node->set('field_gleif_last_update', $current_time->format('Y-m-d\TH:i:s'));

            // Save the node.
            $node->save();
            
            \Drupal::messenger()->addMessage(t('GLEIF data updated successfully for %title.', ['%title' => $node->getTitle()]));
          } else {
            \Drupal::messenger()->addWarning(t('No matching LEI data found for %title.', ['%title' => $node->getTitle()]));
          }
        } else {
          \Drupal::messenger()->addError(t('Failed to fetch LEI data from GLEIF API.'));
        }
      } catch (RequestException $e) {
        \Drupal::messenger()->addError(t('An error occurred while trying to fetch LEI data: %message', ['%message' => $e->getMessage()]));
      }
    } else {
      \Drupal::messenger()->addError(t('Invalid node ID or node is not of type application.'));
    }

    // Redirect back to the node view page.
    return new JsonResponse(['status' => 'success']);
  }

}

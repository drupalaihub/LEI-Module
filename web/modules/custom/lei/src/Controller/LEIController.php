<?php

namespace Drupal\lei\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Class LEIController.
 *
 * Provides route responses for the LEI module.
 */
class LEIController extends ControllerBase {

  /**
   * Fetches and updates GLEIF data for a specific application.
   *
   * @param int $nid
   *   The node ID of the application.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the application node view page.
   */
  public function updateGleifData($nid) {
    // Load the node.
    $node = Node::load($nid);

    if ($node && $node->bundle() === 'application') {
      // Get the registration number and country code from the node.
      $registration_number = $node->get('field_registration_number')->value;
      $country_code = $node->get('field_country_code')->value;

      // Construct the API request URL.
      $api_url = $this->buildApiUrl($registration_number, $country_code);

      try {
        // Perform the API request.
        $response = $this->fetchGleifData($api_url);

        if ($response) {
          $data = json_decode($response->getBody(), TRUE);

          // Extract and update the node with the new data.
          $this->updateNodeWithGleifData($node, $data);

          $this->messenger()->addMessage($this->t('GLEIF data updated successfully for %title.', ['%title' => $node->getTitle()]));
        } else {
          $this->messenger()->addError($this->t('Failed to fetch LEI data from GLEIF API.'));
        }
      } catch (RequestException $e) {
        $this->messenger()->addError($this->t('An error occurred while trying to fetch LEI data: %message', ['%message' => $e->getMessage()]));
      }
    } else {
      $this->messenger()->addError($this->t('Invalid node ID or node is not of type application.'));
    }

    // Redirect back to the application list page.
    $url = Url::fromUri('internal:/applications');
    return new RedirectResponse($url->toString());
  }

  /**
   * Builds the GLEIF API URL.
   *
   * @param string $registration_number
   *   The registration number.
   * @param string $country_code
   *   The country code.
   *
   * @return string
   *   The constructed API URL.
   */
  private function buildApiUrl($registration_number, $country_code) {
    return "https://api.gleif.org/api/v1/lei-records?filter[entity.jurisdiction]={$country_code}&filter[entity.registeredAs]={$registration_number}";
  }

  /**
   * Fetches GLEIF data from the API.
   *
   * @param string $api_url
   *   The API URL.
   *
   * @return \Psr\Http\Message\ResponseInterface|bool
   *   The API response or FALSE on failure.
   */
  private function fetchGleifData($api_url) {
    try {
      return \Drupal::httpClient()->get($api_url, [
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);
    } catch (RequestException $e) {
      \Drupal::logger('lei')->error('Error fetching GLEIF data: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Updates the node with the GLEIF data.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node to update.
   * @param array $data
   *   The GLEIF data.
   */
  private function updateNodeWithGleifData(Node $node, array $data) {
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
    } else {
      $this->messenger()->addWarning($this->t('No matching LEI data found for %title.', ['%title' => $node->getTitle()]));
    }
  }

}

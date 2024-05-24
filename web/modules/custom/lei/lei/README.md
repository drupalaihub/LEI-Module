# LEI Module

This module provides functionality to manage LEI data for applications in a Drupal site. It includes a custom content type with specific fields, a view to display the data, and a function to update LEI data from the GLEIF API.

## Installation

### Step 1: Place the Module in the Custom Directory

Copy the `lei` module directory to your Drupal site's `modules/custom` directory.

<drupal-root>/modules/custom/lei

### Step 2: Enable the Module

Enable the module using Drush or the Drupal admin UI.

**Using Drush:**
drush en lei -y

### Step 3: Import Configuration(Optional)
Import the configuration to set up the content type, fields, and views.

Using Drush:
drush cim -y


### Step 4: Clear Cache
Clear the cache to ensure that all configurations and new code are loaded properly.

Using Drush:
drush cr

Functionality
Content Type and Fields
This module creates a new content type named application with the following fields:

Company name (title)
Registration number - Plain text, 255 characters
Country code - Plain text, 2 characters
LEI code - Plain text, maximum 20 characters
Next renewal date - Date only field
LEI status - Plain text, 255 characters
GLEIF last update - Date field with both date and time

Update GLEIF Data Function
The module provides a function to update GLEIF data for a specific application. You can access this function via a URL where the node ID is an argument.

URL format:
/admin/config/services/lei/update/{nid}
Replace {nid} with the node ID of the application.

API Request Format

The function uses the following API request format to fetch data from the GLEIF API:
https://api.gleif.org/api/v1/lei-records?filter[entity.jurisdiction]=<country_code>&filter[entity.registeredAs]=<registration_number>

Make sure to replace <country_code> and <registration_number> with the actual values from the application node.

View
A view named applications is included to list the applications with the following fields:
Application company name (title)
LEI code
LEI status (colored green if "ISSUED", red if "LAPSED", or yellow otherwise)
Next renewal date
A link to fetch and update the LEI data from the GLEIF API

Additional Notes
Ensure that the module directory structure is correct:

modules/
  custom/
    lei/
      config/
        install/
          core.entity_form_display.node.application.default.yml
          core.entity_view_display.node.application.default.yml
          core.entity_view_display.node.application.teaser.yml
          field.field.node.application.field_registration_number.yml
          field.field.node.application.field_country_code.yml
          field.field.node.application.field_lei_code.yml
          field.field.node.application.field_next_renewal_date.yml
          field.field.node.application.field_lei_status.yml
          field.field.node.application.field_gleif_last_update.yml
          field.storage.node.field_registration_number.yml
          field.storage.node.field_country_code.yml
          field.storage.node.field_lei_code.yml
          field.storage.node.field_next_renewal_date.yml
          field.storage.node.field_lei_status.yml
          field.storage.node.field_gleif_last_update.yml
          node.type.application.yml
          views.view.applications.yml

      src/
        Controller/
          LEIController.php
      lei.info.yml
      lei.install
      lei.module
      README.md



Testing the Module

Step 1: Create a Test Application Node
Go to Content > Add content > Application and create a new node with test data for Registration number and Country code.

Step 2: Update GLEIF Data
Access the update URL:
/admin/config/services/lei/update/{nid}
Replace {nid} with the node ID of the test application node you created. Verify that the LEI data is updated correctly.

Step 3: To access the  viewable listing of the applications we have "Listing application" view, visit your Drupal site's URL followed by /applications
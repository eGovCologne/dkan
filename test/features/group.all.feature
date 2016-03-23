@api @javascript
Feature: Site Manager administer groups
  In order to manage site organization
  As a Site Manager
  I want to administer groups

  Site managers needs to be able to create, edit, and delete
  groups. They need to be able to set group membership by adding and removing
  users and setting group roles and permissions.


  Background:
    Given pages:
      | name      | url             |
      | Groups    | /groups         |
      | Content   | /admin/content/ |
    Given users:
      | name    | mail                | roles         |
      | John    | john@example.com    | site manager  |
      | Badmin  | admin@example.com   | site manager  |
      | Gabriel | gabriel@example.com | editor        |
      | Jaz     | jaz@example.com     | editor        |
      | Katie   | katie@example.com   | editor        |
      | Martin  | martin@example.com  | editor        |
      | Celeste | celeste@example.com | editor        |
    Given groups:
      | title    | author | published |
      | Group 01 | Badmin | Yes       |
      | Group 02 | Badmin | Yes       |
      | Group 03 | Badmin | No        |
    And group memberships:
      | user    | group    | role on group        | membership status |
      | Gabriel | Group 01 | administrator member | Active            |
      | Katie   | Group 01 | member               | Active            |
      | Jaz     | Group 01 | member               | Pending           |
      | Celeste | Group 02 | member               | Active            |
    And "Tags" terms:
      | name    |
      | Health  |
      | Gov     |
      | Count   |
    And datasets:
      | title      | publisher | tags         | author  | published | description                | date changed      |
      | Dataset 01 | Group 01  | Health       | Katie   | Yes       | Increase of toy prices     | 10 September 2015 |
      | Dataset 02 | Group 01  | Health       | Katie   | No        | Cost of oil in January     | 10 September 2015 |
      | Dataset 03 | Group 01  | Gov          | Gabriel | Yes       | Election districts         | 17 October 2015   |
      | Dataset 04 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 10 September 2015 |
      | Dataset 05 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 21 September 2015 |
      | Dataset 06 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 13 March 2015     |
      | Dataset 07 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 10 September 2015 |
      | Dataset 08 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 25 February 2014  |
      | Dataset 09 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 13 September 2014 |
      | Dataset 10 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 10 October 2013   |
      | Dataset 11 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 19 October 2013   |
      | Dataset 12 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 19 October 2013   |
      | Dataset 13 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 23 October 2013   |
      | Dataset 14 | Group 02  | Count        | Celeste | Yes       | Test dataset counts        | 10 September 2015 |
    And resources:
      | title       | publisher | format | author | published | dataset    | description |
      | Resource 01 | Group 01  | csv    | Katie  | Yes       | Dataset 01 |             |
      | Resource 02 | Group 01  | html   | Katie  | Yes       | Dataset 03 |             |


  Scenario: View the list of published groups
    Given I am on the homepage
    When I follow "Groups"
    And I should not see "Group 03"

  Scenario: View the details of a published group
    Given I am on "Groups" page
    When I follow "Group 01"
    #TODO : What should be tested to confirm seeing the page details?
    Then I should be on the "Group 01" page

  Scenario: View the list of datasets on a group
    Given I am on "Group 01" page
    Then I should see "2 datasets" in the "content" region

  Scenario: View the correct count of datasets
    Given I am on "Groups" page
    Then I should see "11 datasets"
    When I click "11 datasets"
    Then I should see "Displaying 1 - 10 of 11 datasets"

  Scenario: View the list of group members
    Given I am on "Group 01" page
    When I click "Members" in the "group block" region
    Then I should see "Gabriel" in the "group members" region
    And I should see "Katie" in the "group members" region
    And I should not see "Jaz" in the "group members" region
    And I should not see "John" in the "group members" region

  Scenario: Search datasets on group
    Given I am on "Group 01" page
    When I fill in "toy" for "Search" in the "content" region
    And I press "Apply"
    Then I wait for "1 datasets"

  Scenario: View available "resource format" filters after search
    Given I am on "Group 01" page
    When I fill in "Dataset" for "Search" in the "content" region
    And I press "Apply"
    Then I should see "csv (1)" in the "filter by resource format" region
    And I should see "html (1)" in the "filter by resource format" region

  Scenario: View available "author" filters after search
    Given I am on "Group 01" page
    When I fill in "Dataset" for "Search" in the "content" region
    And I press "Apply"
    Then I should see "Katie (1)" in the "filter by author" region
    And I should see "Gabriel (1)" in the "filter by author" region

  Scenario: View available "tag" filters after search
    Given I am on "Group 01" page
    When I fill in "Dataset" for "Search" in the "content" region
    And I press "Apply"
    Then I should see "Health (1)" in the "filter by tag" region
    And I should see "Gov (1)" in the "filter by tag" region

  Scenario: View available "date changed" filters after search
    Given I am on "Group 01" page
    Then I should see "September 10, 2015 (1)" in the "filter by date changed" region
    And I should see "October 17, 2015 (1)" in the "filter by date changed" region

  Scenario: Filter datasets on group by resource format
    Given I am on "Group 01" page
    When I fill in "Dataset" for "Search" in the "content" region
    And I press "Apply"
    When I click "csv (1)" in the "filter by resource format" region
    Then I wait for "1 datasets"

  Scenario: Filter datasets on group by author
    Given I am on "Group 01" page
    When I fill in "Dataset" for "Search" in the "content" region
    And I press "Apply"
    When I click "Katie" in the "filter by author" region
    Then I wait for "1 datasets"

  Scenario: Filter datasets on group by tags
    Given I am on "Group 01" page
    When I fill in "Dataset" for "Search" in the "content" region
    And I press "Apply"
    When I click "Health" in the "filter by tag" region
    Then I wait for "1 datasets"

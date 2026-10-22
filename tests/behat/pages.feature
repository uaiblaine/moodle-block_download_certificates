@block @block_download_certificates
Feature: The certificate pages list what the viewer's scope reaches
  In order to download certificates in bulk
  As a manager or a teacher
  I need the page of my scope to list its certificates, and no others

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Mia       | Manager  | manager1@example.com |
      | teacher1 | Tom       | Teacher  | teacher1@example.com |
      | learner1 | Ada       | Learner  | learner1@example.com |
      | learner2 | Bob       | Learner  | learner2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course A | CA        |
      | Course B | CB        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | CA     | editingteacher |
      | learner1 | CA     | student        |
      | learner1 | CB     | student        |
      | learner2 | CB     | student        |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |
    And the following certificate templates exist:
      | name        |
      | Alpha Award |
      | Beta Badge  |
    And the following certificate issues exist for the download certificates block:
      | template    | user     | course | code |
      | Alpha Award | learner1 | CA     | BDC1 |
      | Beta Badge  | learner1 | CB     | BDC2 |
      | Beta Badge  | learner2 | CB     | BDC3 |

  @javascript
  Scenario: A manager lists every certificate, searches them and switches the download tab
    Given I log in as "manager1"
    When I visit "/blocks/download_certificates/index.php"
    Then I should see "Alpha Award"
    And I should see "Beta Badge"
    And I should not see "Download Course Certificates"
    When I set the field "Search certificates" to "Alpha"
    Then I should see "Alpha Award"
    And I should not see "Beta Badge"
    When I click on "By course" "link"
    Then I should see "Download Course Certificates"
    And I should not see "Download Range"

  @javascript
  Scenario: A manager can cancel the confirmation of downloading everything
    Given I log in as "manager1"
    And I visit "/blocks/download_certificates/index.php"
    When I click on "Download All Certificates" "button"
    Then I should see "Are you sure you want to download all certificates? This may take some time." in the "Confirm" "dialogue"
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    And I should not see "Are you sure you want to download all certificates?"
    And I should see "Alpha Award"

  @javascript
  Scenario: A teacher lists the certificates of their own course only
    Given I log in as "teacher1"
    When I visit "/blocks/download_certificates/index_teacher.php"
    Then I should see "Alpha Award"
    And I should not see "Beta Badge"

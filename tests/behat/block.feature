@block @block_download_certificates
Feature: The block summarises the certificates of its viewer on the Dashboard
  In order to see my certificates without opening a page
  As a learner or a manager
  I need the block on the Dashboard to count what my scope reaches

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Mia       | Manager  | manager1@example.com |
      | learner1 | Ada       | Learner  | learner1@example.com |
      | learner2 | Cy        | Learner  | learner2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course A | CA        |
      | Course B | CB        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | learner1 | CA     | student |
      | learner1 | CB     | student |
      | learner2 | CB     | student |
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
    And the following "blocks" exist:
      | blockname             | contextlevel | reference | pagetypepattern | defaultregion |
      | download_certificates | System       | 1         | my-index        | side-pre      |
    # HOMEPAGE_MY, so logging in lands on the Dashboard on every branch.
    And the following config values are set as admin:
      | defaulthomepage | 1 |

  Scenario: A learner sees the count of their own certificates only
    When I log in as "learner1"
    Then I should see "My certificates" in the ".block_download_certificates" "css_element"
    And I should see "2" in the ".block_download_certificates .block-download-certificates-number" "css_element"
    And I should see "Download My Certificates" in the ".block_download_certificates" "css_element"
    And I should not see "Certificate Summary" in the ".block_download_certificates" "css_element"

  Scenario: A manager sees the summary of the whole site
    When I log in as "manager1"
    Then I should see "Certificate Summary" in the ".block_download_certificates" "css_element"
    And I should see "3" in the ".block_download_certificates .block-download-certificates-stats .col:first-child .block-download-certificates-number" "css_element"
    And I should see "Manage Certificates" in the ".block_download_certificates" "css_element"
    And I should see "Download All" in the ".block_download_certificates" "css_element"

@mod @mod_quiz @quizaccess @quizaccess_fsquiz @javascript
Feature: Fullscreen lockdown quiz auto-submits when the student leaves the window
  As a teacher
  In order to stop students consulting other resources during a locked-down quiz
  I need attempts to be auto-submitted the moment a student leaves the quiz window

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name   | course | idnumber | grade | fsquizenabled | fsquizgraceperiod |
      | quiz     | Quiz 1 | C1     | quiz1    | 100   | 1             | 300               |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | quiz1     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | First question  |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |

  Scenario: A student's attempt is auto-submitted and graded after leaving the window
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    And I switch to a second window
    And I should see "Fullscreen lockdown quiz"
    And I set the field "I understand that leaving this window will immediately submit my attempt as it stands." to "1"
    And I press "Start attempt"
    And I click on "True" "radio"
    When I simulate leaving the fullscreen quiz window
    # Grace period is 300ms; give the auto-submit and grading a moment to complete.
    And I wait "2" seconds
    Then I should see "Your attempt was automatically submitted because you left the locked-down quiz window."
    # The popup shows that notice for a few seconds, then closes itself and hands the
    # original window back to the course page.
    And I wait "5" seconds
    And I switch to the main window
    And I reload the page
    Then I should see "Course 1"

  Scenario: Returning to the window before the grace period elapses cancels the auto-submit
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    And I switch to a second window
    And I set the field "I understand that leaving this window will immediately submit my attempt as it stands." to "1"
    And I press "Start attempt"
    And I click on "True" "radio"
    When I simulate leaving the fullscreen quiz window
    And I simulate returning to the fullscreen quiz window
    And I wait "1" second
    Then I should see "True" in the "TF1" "question"
    And I should not see "Your attempt was automatically submitted"

  Scenario: The teacher can see why an attempt was auto-submitted
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
    And the following fsquiz auto-submit logs exist:
      | quiz   | user     | reason | attempt |
      | Quiz 1 | student1 | blur   | 1       |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    When I view the fullscreen lockdown log for the quiz "Quiz 1"
    Then I should see "Student One"
    And I should see "Window lost focus"

  Scenario: A quiz with no auto-submits shows an empty log
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    When I view the fullscreen lockdown log for the quiz "Quiz 1"
    Then I should see "No attempts at this quiz have been auto-submitted due to focus loss."

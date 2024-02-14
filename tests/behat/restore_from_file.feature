@format @format_tiles @format_tiles_restore_from_file
Feature: Backup and restore of fixture mbz files including images

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | format |
      | Course 1 | C1        | 0        | tiles  |

  @javascript @_file_upload
  Scenario: Restore the Moodle 311 mbz file
    Given I am on the "Course 1" "restore" page logged in as "admin"
    And I press "Manage backup files"
    And I upload "course/format/tiles/tests/fixtures/moodle-311-sample.mbz" file to "Files" filemanager
    And I press "Save changes"
    And I restore "moodle-311-sample.mbz" backup into a new course using this options:
      | Schema   | Course name            | Course 2 |
      | Schema   | Course short name      | C2       |

    And I am on "Course 2" course homepage with editing mode off
    And I wait until the page is ready
    And I wait "2" seconds
    And course "Course 2" tile "1" should show photo "placeholder_1.jpg"
    And course "Course 2" tile "2" should show photo "placeholder_2.jpg"
    And course "Course 2" tile "3" should show photo "placeholder_3.jpg"
    And course "Course 2" tile "4" should show photo "placeholder_4.jpg"
    And course "Course 2" tile "5" should show photo "placeholder_5.jpg"
    And course "Course 2" tile "6" should show no photo
    And Tile "6" should have icon "map-o"
    And course "Course 2" tile "7" should show photo "placeholder_7.jpg"
    And course "Course 2" tile "8" should show no photo
    And Tile "8" should have icon "map-signs"
    And course "Course 2" tile "9" should show no photo
    And Tile "9" should have icon "tasks"
    And course "Course 2" tile "10" should show no photo
    And Tile "10" should have icon "bookmark-o"

  @javascript @_file_upload
  Scenario: Restore the Moodle 43 mbz file
    Given I am on the "Course 1" "restore" page logged in as "admin"
    And I press "Manage backup files"
    And I upload "course/format/tiles/tests/fixtures/moodle-43-sample.mbz" file to "Files" filemanager
    And I press "Save changes"
    And I restore "moodle-43-sample.mbz" backup into a new course using this options:
      | Schema   | Course name            | Course 3 |
      | Schema   | Course short name      | C3       |

    And I am on "Course 3" course homepage with editing mode off
    And I wait until the page is ready
    And I wait "2" seconds
    And course "Course 3" tile "1" should show photo "placeholder_1.jpg"
    And course "Course 3" tile "2" should show photo "placeholder_2.jpg"
    And course "Course 3" tile "3" should show photo "placeholder_3.jpg"
    And course "Course 3" tile "4" should show photo "placeholder_4.jpg"
    And course "Course 3" tile "5" should show photo "placeholder_5.jpg"
    And course "Course 3" tile "6" should show no photo
    And Tile "6" should have icon "pie-chart"
    And course "Course 3" tile "7" should show photo "placeholder_7.jpg"
    And course "Course 3" tile "8" should show no photo
    And Tile "8" should have icon "pie-chart"
    And course "Course 3" tile "9" should show no photo
    And Tile "9" should have icon "pie-chart"
    And course "Course 3" tile "10" should show no photo
    And Tile "10" should have icon "pie-chart"

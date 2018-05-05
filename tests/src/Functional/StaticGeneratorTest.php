<?php

namespace Drupal\Tests\static_generator\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;
use Drupal\Component\Serialization\Json;

/**
 * Verifies operation of the Static Generator service.
 *
 * @group tally
 */
class StaticGeneratorTest extends BrowserTestBase {

  /**
   * The Tally member profile fields test values.
   *
   * @var string
   */
  protected $account;
  protected $testFirstName;
  protected $testLastName;
  protected $testGender;
  protected $testBirthDate;
  protected $testPhoneNumber;
  protected $email;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'user',
    'field',
    'field_ui',
    'filter',
    'text',
    'datetime',
    'options',
    'telephone',
    'static_generator',
    'static_generator_test',
  ];

  /**
   * Installation profile.
   *
   * @var string
   */
  /* protected $profile = 'lightning'; */

  /**
   * Disable strict checking.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissionsAdmin = [
    'access administration pages',
    'administer static generator',
    'administer users',
    'administer account settings',
    'administer site configuration',
    'administer user fields',
    'administer user form display',
    'administer user display',
  ];

  /**
   * Permissions to grant tally member.
   *
   * @var array
   */
  protected $permissionsTallyMember = [
    'tally member',
  ];

  /**
   * {@inheritdoc}
   */
  public function setup() {
    parent::setup();

    // Construct endpoint url.
    $endpoint_url = \Drupal::config('tally.settings')->get('endpoint_url')
      . 'members/' . self::TEST_TALLY_MEMBER_ID;

    $this->testFirstName = 'FirstInit';
    $this->testLastName = 'LastInit';
    $this->testGender = 'female';
    $this->testBirthDate = '1972-01-01';
    $this->testPhoneNumber = '111' . strval(rand(1000000, 9999999));

    // Create test member profile with known values.
    $response = \Drupal::service('http_client')->put($endpoint_url,
      [
        'json' =>
          [
            'firstName' => $this->testFirstName,
            'lastName' => $this->testLastName,
            'gender' => $this->testGender,
            'birthDate' => $this->testBirthDate,
            'phoneNumber' => $this->testPhoneNumber,
          ],
        'headers' =>
          [
            'TDWS-Client-Program-Id' => \Drupal::config('tally.settings')
              ->get('tdws_client_program_id'),
            'TDWS-Application-Id' => \Drupal::config('tally.settings')
              ->get('tdws_application_id'),
            'TDWS-User-Id' => \Drupal::config('tally.settings')
              ->get('tdws_user_id'),
          ],
        'cert' => \Drupal::config('tally.settings')->get('ssl_cert'),
        'ssl_key' => \Drupal::config('tally.settings')->get('ssl_key'),
        'allow_redirects' => FALSE,
        'timeout' => 5,
        'verify' => FALSE,
      ]);

    // Create and load a Tally member.
    $this->drupalLogin($this->drupalCreateUser($this->permissionsTallyMember));
    $this->account = User::load(\Drupal::currentUser()->id());

    // Set email address to random string as Tally does not permit duplicate
    // email addresses.
    $this->email = $this->randomMachineName(12) . '@icfolson.com';
    $this->account->setEmail($this->email)->save();

  }

  /**
   * Tests connecting to the Tally REST service.
   */
  public function testRestService() {

    // Get the endpoint.
    $endpoint_url = \Drupal::config('tally.settings')->get('endpoint_url')
      . 'system-information';

    // Get the response.
    $response = \Drupal::httpClient()->get($endpoint_url,
      [
        'verify' => FALSE,
        'headers' =>
          [
            'TDWS-Client-Program-Id' => \Drupal::config('tally.settings')
              ->get('tdws_client_program_id'),
            'TDWS-Application-Id' => \Drupal::config('tally.settings')
              ->get('tdws_application_id'),
            'TDWS-User-Id' => \Drupal::config('tally.settings')
              ->get('tdws_user_id'),
          ],
        'cert' => \Drupal::config('tally.settings')->get('ssl_cert'),
        'ssl_key' => \Drupal::config('tally.settings')->get('ssl_key'),
      ]);

    // Decode the response.
    $json_data = $response->getBody()->getContents();
    $system_information_array = Json::decode($json_data);
    $system_information = $system_information_array['systemInformation']['coreVersion'];
    $this->assertEquals('6.6.1-SNAPSHOT', $system_information);

  }

  /**
   * Tests Tally settings page.
   */
  public function testSettingsPage() {

    // Create and login as Tally admin user.
    $this->drupalLogin($this->drupalCreateUser($this->permissionsAdmin, 'test_tally_admin', TRUE));

    // Verify Tally link is present on config page.
    $this->drupalGet('admin/config');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Tally');

    // Verify that Tally settings page has correct fields.
    $this->drupalGet('admin/config/services/tally');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('REST Endpoint URL');
    $this->assertSession()->pageTextContains('Client Program ID');
    $this->assertSession()->pageTextContains('Application ID');
    $this->assertSession()->pageTextContains('User ID');
    $this->assertSession()->pageTextContains('SSL Certificate');
    $this->assertSession()->pageTextContains('SSL Key');

  }

  /**
   * Tests that the Tally fields are present in the user field configuration.
   */
  public function testUserFieldsAdmin() {

    // Create and login as Tally admin user.
    $this->drupalLogin($this->drupalCreateUser($this->permissionsAdmin, 'test_tally_admin', TRUE));

    // Verify that the fields exist in the admin field ui.
    $this->drupalGet('admin/config/people/accounts/fields');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Account Number');
    $this->assertSession()->pageTextContains('First Name');
    $this->assertSession()->pageTextContains('Last Name');
    $this->assertSession()->pageTextContains('Gender');
    $this->assertSession()->pageTextContains('Birth Date');
    $this->assertSession()->pageTextContains('Phone Number');

    // Get each field's config form.
    $this->drupalGet('admin/config/people/accounts/fields/user.user.field_tally_first_name');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/config/people/accounts/fields/user.user.field_tally_last_name');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/config/people/accounts/fields/user.user.field_tally_gender');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/config/people/accounts/fields/user.user.field_tally_birth_date');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/config/people/accounts/fields/user.user.field_tally_phone_number');
    $this->assertSession()->statusCodeEquals(200);

  }

  /**
   * Tests that the Tally fields are present in the user profile.
   */
  public function testUserFieldsMember() {

    // Verify Tally profile fields are available on the Drupal user profile
    // edit form.
    $this->drupalGet('user/' . \Drupal::currentUser()->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Username');
    $this->assertSession()->pageTextContains('Password');
    $this->assertSession()->pageTextContains('Confirm password');
    $this->assertSession()->pageTextContains('First Name');
    $this->assertSession()->pageTextContains('Last Name');
    $this->assertSession()->pageTextContains('Gender');
    $this->assertSession()->pageTextContains('Birth Date');
    $this->assertSession()->pageTextContains('Phone Number');

  }

  /**
   * Tests updating the profile fields from Drupal to the Tally REST service.
   */
  public function testEnroll() {

    $phone_number = '222' . strval(rand(1000000, 9999999));

    // Change field values.
    $edit = [];
    $edit['field_tally_first_name[0][value]'] = t('FirstEnroll');
    $edit['field_tally_last_name[0][value]'] = t('LastEnroll');
    /* $edit['edit-field-tally-gender'] = t('male'); */
    $edit['field_tally_birth_date[0][value][date]'] = '1967-01-01';
    $edit['field_tally_phone_number[0][value]'] = $phone_number;
    $this->drupalPostForm('user/' . $this->account->id() . '/edit', $edit, t('Save'));

    // Load user.
    $this->account = User::load(\Drupal::currentUser()->id());

    // Verify member_id set.
    $this->assertNotEmpty($this->account->get('field_tally_member_id')->value);

    // Verify original field values.
    $this->assertEquals(t('FirstEnroll'), $this->account->get('field_tally_first_name')->value);
    $this->assertEquals(t('LastEnroll'), $this->account->get('field_tally_last_name')->value);
    /* $this->assertEquals(t('male'), $this->account->get('field_tally_gender')->value); */
    $this->assertEquals('1967-01-01', $this->account->get('field_tally_birth_date')->value);
    $this->assertEquals($phone_number, $this->account->get('field_tally_phone_number')->value);

  }

  /**
   * Tests updating the profile fields from Drupal to the Tally REST service.
   */
  public function testUpdate() {

    // Set the Tally account number to one with known field values.
    $this->account->set('field_tally_member_id', self::TEST_TALLY_MEMBER_ID)->save();

    // Generate test field values to update.
    $first_name = 'FirstUpdate';
    $last_name = 'LastUpdate';
    $gender = 'male';
    $birth_date = '1999-01-01';
    $phone_number = '333' . strval(rand(1000000, 9999999));

    // Change field values.
    $edit = [];
    $edit["field_tally_first_name[0][value]"] = $first_name;
    $edit['field_tally_last_name[0][value]'] = $last_name;
    $edit['field_tally_gender'] = $gender;
    $edit['field_tally_birth_date[0][value][date]'] = $birth_date;
    $edit['field_tally_phone_number[0][value]'] = $phone_number;
    $this->drupalPostForm('user/' . $this->account->id() . '/edit', $edit, t('Save'));

    // Load user.
    $this->account = User::load($this->account->id());

    // Refresh the Tally member profile.
    \Drupal::service('tally.member_profile')->refresh($this->account);

    // Load user.
    $this->account = User::load(\Drupal::currentUser()->id());

    // Verify updated field values.
    $this->assertEquals($first_name, $this->account->get('field_tally_first_name')->value);
    $this->assertEquals($last_name, $this->account->get('field_tally_last_name')->value);
    $this->assertEquals($gender, $this->account->get('field_tally_gender')->value);
    $this->assertEquals($birth_date, $this->account->get('field_tally_birth_date')->value);
    $this->assertEquals($phone_number, $this->account->get('field_tally_phone_number')->value);

  }

  /**
   * Tests updating the Tally member profile last name field.
   */
  public function testUpdateLastName() {

    // Set the Tally account number to one with known field values.
    $this->account->set('field_tally_member_id', self::TEST_TALLY_MEMBER_ID)->save();

    // Change last name.
    $edit = [];
    $edit['field_tally_last_name[0][value]'] = t('UpdateLastNameOnly');
    $this->drupalPostForm('user/' . $this->account->id() . '/edit', $edit, t('Save'));

    // Load user object.
    $this->account = User::load($this->account->id());

    // Refresh the Tally member profile.
    \Drupal::service('tally.member_profile')->refresh();

    // Get last name.
    $last_name = $this->account->get('field_tally_last_name')->value;

    // Verify last name was updated.
    $this->assertEquals(t('UpdateLastNameOnly'), $last_name);

  }

  /**
   * Tests refreshing the Tally member profile from the Tally REST service.
   */
  public function testRefresh() {

    // Set the Tally account number to one with known field values.
    $this->account->set('field_tally_member_id', self::TEST_TALLY_MEMBER_ID)->save();

    // Refresh the test Tally member's profile.
    \Drupal::service('tally.member_profile')->refresh();

    // Load user.
    $this->account = User::load(\Drupal::currentUser()->id());

    // Verify member profile field data was refreshed from Tally.
    $first_name = $this->account->get('field_tally_first_name')->value;
    $last_name = $this->account->get('field_tally_last_name')->value;
    $gender = $this->account->get('field_tally_gender')->value;
    $birth_date = $this->account->get('field_tally_birth_date')->value;
    $phone_number = $this->account->get('field_tally_phone_number')->value;
    $this->assertEquals($this->testFirstName, $first_name);
    $this->assertEquals($this->testLastName, $last_name);
    $this->assertEquals($this->testGender, $gender);
    $this->assertEquals($this->testBirthDate, $birth_date);
    $this->assertEquals($this->testPhoneNumber, $phone_number);

  }

  /**
   * Tests refreshing the Tally member profile from the Tally REST service.
   */
  public function testRefreshPointBalance() {

    // Adjust total points. @TODO is adjustPointBalance needed/secure?
    /* \Drupal::service('tally.member_profile')->adjustPointBalance(99, 'TQPFixed', 'Test adjusting member point balance.'); */

    // Set the Tally account number to one with known field values.
    $this->account->set('field_tally_member_id', self::TEST_TALLY_MEMBER_ID)->save();

    // Refresh total points.
    \Drupal::service('tally.member_profile')->refreshPointBalance();

    // Load user.
    $this->account = User::load(\Drupal::currentUser()->id());

    // Verify points > 0.
    $point_balance = $this->account->get('field_tally_point_balance')->value;
    $this->assertGreaterThan(0, $point_balance);

  }

}

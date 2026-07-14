<?php

namespace Tests\Feature;

use App\CentralLogics\AccountTypeLogic;
use App\User;
use Tests\TestCase;

class CustomerAccountTypeTest extends TestCase
{
    public function test_account_type_logic_labels(): void
    {
        $this->assertSame('Mentor login', AccountTypeLogic::loginPortalLabel('mentor'));
        $this->assertSame('Student login', AccountTypeLogic::loginPortalLabel('mentee'));
        $this->assertSame('Google', AccountTypeLogic::loginMediumLabel('google'));
        $this->assertSame('Email / Phone', AccountTypeLogic::loginMediumLabel('general'));
    }

    public function test_validate_login_portal_rejects_mentee_tab_for_mentor_account(): void
    {
        $user = new User(['account_type' => 'mentor']);

        $response = AccountTypeLogic::validateLoginPortal($user, 'mentee');

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('wrong_login_portal', $response->getData(true)['errors'][0]['code']);
    }

    public function test_validate_login_portal_allows_matching_portal(): void
    {
        $user = new User(['account_type' => 'mentor']);

        $this->assertNull(AccountTypeLogic::validateLoginPortal($user, 'mentor'));
    }

    public function test_registration_account_type_defaults_to_mentee(): void
    {
        $this->assertSame('mentee', AccountTypeLogic::accountTypeForRegistration(null));
        $this->assertSame('mentee', AccountTypeLogic::accountTypeForRegistration('mentee'));
        $this->assertSame('mentor', AccountTypeLogic::accountTypeForRegistration('mentor'));
    }

    public function test_admin_customer_reset_password_route_is_registered(): void
    {
        $this->assertNotNull(route('admin.customer.reset-password', ['id' => 1], absolute: false));
    }
}

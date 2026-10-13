<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\Services\Shared\Validation;
use App\Application\Validation\Auth\PasswordValidator;
use App\Application\Validation\Auth\UsernameValidator;
use App\Application\Validation\Shared\MaxLengthValidator;
use App\Application\Validation\Shared\MinLengthValidator;
use App\Application\Validation\Shared\RegexValidator;
use App\Application\Validation\Shared\RequiredValidator;
use PHPUnit\Framework\TestCase;

final class ValidationServiceTest extends TestCase
{
    private Validation $validation;

    protected function setUp(): void
    {
        $this->validation = new Validation();
    }

    public function testRequiredValidatorRejectsEmptyValue(): void
    {
        $validator = new RequiredValidator('Username is required.');

        self::assertSame('Username is required.', $validator->validate(''));
        self::assertSame('Username is required.', $validator->validate(null));
        self::assertNull($validator->validate('alice'));
    }

    public function testMinLengthValidator(): void
    {
        $validator = new MinLengthValidator(8, 'Password must be at least 8 characters.');

        self::assertSame(
            'Password must be at least 8 characters.',
            $validator->validate('short')
        );
        self::assertNull($validator->validate('password1'));
    }

    public function testMaxLengthValidator(): void
    {
        $validator = new MaxLengthValidator(5, 'Too long.');

        self::assertSame('Too long.', $validator->validate('abcdef'));
        self::assertNull($validator->validate('abc'));
    }

    public function testUsernameValidatorTrimsAndValidates(): void
    {
        $validator = new UsernameValidator();

        $valid = $validator->validate('  alice  ');
        self::assertTrue($valid->isValid());
        self::assertSame('alice', $valid->getValue());

        $empty = $validator->validate('   ');
        self::assertFalse($empty->isValid());
        self::assertSame('Username is required.', $empty->getError());
    }

    public function testRegexValidator(): void
    {
        $validator = new RegexValidator(
            PasswordValidator::PATTERN,
            PasswordValidator::PATTERN_MESSAGE
        );

        self::assertSame(
            PasswordValidator::PATTERN_MESSAGE,
            $validator->validate('Password1')
        );
        self::assertNull($validator->validate('Password1!'));
    }

    public function testPasswordValidatorEnforcesLengthAndPattern(): void
    {
        $validator = new PasswordValidator();

        $short = $validator->validate('short');
        self::assertFalse($short->isValid());
        self::assertSame(
            'Password must be at least 8 characters.',
            $short->getError()
        );

        $weak = $validator->validate('password');
        self::assertFalse($weak->isValid());
        self::assertSame(
            PasswordValidator::PATTERN_MESSAGE,
            $weak->getError()
        );

        $valid = $validator->validate('Password1!');
        self::assertTrue($valid->isValid());
    }

    public function testValidLoginReturnsNormalizedDto(): void
    {
        $result = $this->validation->validateLogin('  alice  ', 'simple');

        self::assertTrue($result->isValid());
        self::assertSame([], $result->getErrors());
        self::assertNotNull($result->getLoginRequest());
        self::assertSame('alice', $result->getLoginRequest()->getUsername());
        self::assertSame('simple', $result->getLoginRequest()->getPassword());
    }

    public function testRejectsEmptyUsernameAndPassword(): void
    {
        $result = $this->validation->validateLogin('   ', '');

        self::assertFalse($result->isValid());
        self::assertSame('Username is required.', $result->getError('user_login'));
        self::assertSame('Password is required.', $result->getError('user_password'));
        self::assertNull($result->getLoginRequest());
    }

    public function testRejectsNonStringFields(): void
    {
        $result = $this->validation->validateLogin(['alice'], ['secret']);

        self::assertFalse($result->isValid());
        self::assertSame('Username is required.', $result->getError('user_login'));
        self::assertSame('Password is required.', $result->getError('user_password'));
    }

    public function testLoginDoesNotEnforcePasswordComplexity(): void
    {
        $result = $this->validation->validateLogin('alice', 'short');

        self::assertTrue($result->isValid());
        self::assertNotNull($result->getLoginRequest());
        self::assertSame('short', $result->getLoginRequest()->getPassword());
    }

    public function testRejectsOversizedUsername(): void
    {
        $username = str_repeat('a', UsernameValidator::MAX_LENGTH + 1);
        $result = $this->validation->validateLogin($username, 'Password1!');

        self::assertFalse($result->isValid());
        self::assertSame(
            'Username must be at most 255 characters.',
            $result->getError('user_login')
        );
    }

    public function testRejectsOversizedPassword(): void
    {
        $password = str_repeat('p', PasswordValidator::MAX_LENGTH + 1);
        $result = $this->validation->validateLogin('alice', $password);

        self::assertFalse($result->isValid());
        self::assertSame(
            'Password must be at most 72 characters.',
            $result->getError('user_password')
        );
    }

    public function testGetFirstErrorReturnsUsernameFirst(): void
    {
        $result = $this->validation->validateLogin('', '');

        self::assertSame('Username is required.', $result->getFirstError());
    }

    public function testValidRegisterReturnsNormalizedDto(): void
    {
        $result = $this->validation->validateRegister(
            '  alice  ',
            '  alice@example.com  ',
            'Password1!',
            'Password1!'
        );

        self::assertTrue($result->isValid());
        self::assertNotNull($result->getRegisterRequest());
        self::assertSame('alice', $result->getRegisterRequest()->getUsername());
        self::assertSame('alice@example.com', $result->getRegisterRequest()->getEmail());
        self::assertSame('Password1!', $result->getRegisterRequest()->getPassword());
    }

    public function testRegisterRejectsInvalidEmailAndPasswordMismatch(): void
    {
        $result = $this->validation->validateRegister(
            'alice',
            'not-an-email',
            'Password1!',
            'Password2!'
        );

        self::assertFalse($result->isValid());
        self::assertSame('Email format is invalid.', $result->getError('user_mail'));
        self::assertSame(
            'Password confirmation does not match.',
            $result->getError('password_confirm')
        );
    }
}

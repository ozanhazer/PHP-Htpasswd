<?php

namespace tests;

use Exception;
use Htpasswd;
use InvalidArgumentException;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HtpasswdTest extends TestCase
{
    private string $originalFile = __DIR__ . '/valid_htpasswd.txt';
    private string $passFile = __DIR__ . '/htpasswd_on_test.txt';

    protected function setUp(): void
    {
        copy($this->originalFile, $this->passFile);
    }

    public function tearDown(): void
    {
        unlink($this->passFile);
    }

    #[Test]
    public function init_without_filename_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Htpasswd('');
    }

    #[Test]
    public function throw_exception_if_password_file_could_not_be_found(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Password file could not be found');

        new Htpasswd('some_file');
    }

    #[Test]
    public function get_all_users_and_passwords(): void
    {
        $htpasswd = new Htpasswd($this->passFile);
        $users = $htpasswd->getUsers();

        $this->assertSame(array(
            'test_user1' => 'adfasfd',
            'test_user2' => 'adfasfd',
            'test_user3' => 'adfasfd',
        ), $users);
    }

    #[Test]
    public function check_user_exists(): void
    {
        $htpasswd = new Htpasswd($this->passFile);
        $this->assertTrue($htpasswd->userExists('test_user1'));
        $this->assertFalse($htpasswd->userExists('invalid'));
    }

    #[Test]
    public function update_user_method_should_be_used_while_adding_user(): void
    {
        $mock = $this->getMockBuilder(Htpasswd::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['updateUser'])
            ->getMock();

        $mock->expects($this->once())
            ->method('updateUser')
            ->with('new_user', '12345678')
            ->willReturn(true);

        $returnVal = $mock->addUser('new_user', '12345678');
        $this->assertTrue($returnVal);
    }

    #[Test]
    public function dont_update_anything_while_adding_user_if_the_user_already_exists(): void
    {
        $mock = $this->getMockBuilder(Htpasswd::class)
            ->setConstructorArgs([$this->passFile])
            ->onlyMethods(['updateUser'])
            ->getMock();

        $mock->expects($this->never())
            ->method('updateUser');

        $returnVal = $mock->addUser('test_user1', '12345768');

        $this->assertFalse($returnVal);
        $this->assertFileEquals($this->originalFile, $this->passFile);
    }

    #[Test]
    #[DataProvider('invalidUserNames')]
    public function validate_username_while_updating(string $user, string $error): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($error);

        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->updateUser($user, '12345768');
    }

    #[Test]
    public function update_user_auto_creates_user_if_not_exists(): void
    {
        $htpasswd = new Htpasswd($this->passFile);
        $returnVal = $htpasswd->updateUser('new_user', '12345768');

        $this->assertTrue($returnVal);
        $content = file($this->passFile);
        $lastLine = end($content);
        $this->assertCount(4, $content);
        $this->assertStringStartsWith('new_user:', $lastLine);
    }

    #[Test]
    #[DataProvider('validAlgorithms')]
    public function update_user(string $encType): void
    {
        $htpasswd = new Htpasswd($this->passFile);
        $returnVal = $htpasswd->updateUser('test_user1', '12345678', $encType);

        $this->assertTrue($returnVal);

        // The file should be updated
        $content = file($this->passFile);
        $this->assertCount(3, $content);

        [$user, $pass] = explode(':', $content[0]);
        $pass = trim($pass);

        $this->assertSame('test_user1', $user);
        $this->assertNotEquals('adfasfd', $pass);

        // getUsers method should return the updated results
        $users = $htpasswd->getUsers();
        $this->assertSame($pass, $users['test_user1']);

        // Validate encryption type
        $this->assertEncType($encType, $pass, '12345678');
    }

    #[Test]
    public function invalid_encryption_should_throw_an_exception(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid encryption type');

        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->updateUser('test_user1', '12345768', 'invalid');
    }

    #[Test]
    public function crypt_is_the_default_encryption_type(): void
    {
        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->updateUser('test_user1', '87654321');

        $users = $htpasswd->getUsers();
        $pass = $users['test_user1'];
        $this->assertEncType(Htpasswd::ENCTYPE_CRYPT, $pass, '87654321');
    }

    #[Test]
    public function trigger_notice_if_password_is_too_long_for_crypt(): void
    {
        $noticed = false;
        set_error_handler(function (int $errno) use (&$noticed): bool {
            if ($errno === E_USER_NOTICE) {
                $noticed = true;
            }
            return true;
        });

        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->updateUser('test_user1', '1234567812345678');

        restore_error_handler();
        $this->assertTrue($noticed, "Expected E_USER_NOTICE for passwords longer than 8 characters with crypt");
    }

    #[Test]
    public function delete_user(): void
    {
        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->deleteUser('test_user2');

        $users = $htpasswd->getUsers();
        $this->assertCount(2, $users);

        $content = file($this->passFile);
        $this->assertCount(2, $content);
        $this->assertNotContains("test_user2:adfasfd\n", $content);
    }

    #[Test]
    public function throw_exception_if_non_existent_user_is_tried_to_be_deleted(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User not found');

        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->deleteUser('asdfasd');
    }

public static function invalidUserNames(): array
    {
        return [
            ['user:invalid', 'Invalid username. Username cannot contain colon (:) character'],
            [':leading_colon', 'Invalid username. Username cannot contain colon (:) character'],
            [str_repeat('x', 257), 'Usernames cannot be longer than 256 bytes'],
        ];
    }

    public static function validAlgorithms(): array
    {
        return [
            [Htpasswd::ENCTYPE_APR_MD5],
            [Htpasswd::ENCTYPE_CRYPT],
            [Htpasswd::ENCTYPE_SHA1],
        ];
    }

    private function assertEncType(string $encType, string $encryptedPass, string $plainPass): void
    {
        switch ($encType) {
            case Htpasswd::ENCTYPE_CRYPT:
                $this->assertTrue(hash_equals($encryptedPass, crypt($plainPass, $encryptedPass)));
                break;
            case Htpasswd::ENCTYPE_APR_MD5:
                $this->assertStringStartsWith('$apr1$', $encryptedPass);
                break;
            case Htpasswd::ENCTYPE_SHA1:
                $str = '{SHA}' . base64_encode(sha1($plainPass, true));
                $this->assertSame($str, $encryptedPass);
                break;
            default:
                $this->fail('Invalid enctype: ' . $encType);
        }
    }

}

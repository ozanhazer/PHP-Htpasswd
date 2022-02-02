<?php

namespace tests;

use Exception;
use Htpasswd;
use InvalidArgumentException;
use DomainException;
use PHPUnit\Framework\TestCase;

class HtpasswdTest extends TestCase
{
    private $originalFile = __DIR__ . '/valid_htpasswd.txt';
    private $passFile = __DIR__ . '/htpasswd_on_test.txt';

    protected function setUp(): void
    {
        copy($this->originalFile, $this->passFile);
    }

    public function tearDown(): void
    {
        unlink($this->passFile);
    }

    /**
     * @test
     */
    public function init_without_filename_throws_exception()
    {
        $this->expectException(InvalidArgumentException::class);

        new Htpasswd('');
    }

    /**
     * @test
     */
    public function throw_exception_if_password_file_could_not_be_found()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Password file could not be found');

        new Htpasswd('some_file');
    }

    /**
     * @test
     */
    public function get_all_users_and_passwords()
    {
        $htpasswd = new Htpasswd($this->passFile);
        $users = $htpasswd->getUsers();

        $this->assertSame(array(
            'test_user1' => 'adfasfd',
            'test_user2' => 'adfasfd',
            'test_user3' => 'adfasfd',
        ), $users);
    }

    /**
     * @test
     */
    public function check_user_exists()
    {
        $htpasswd = new Htpasswd($this->passFile);
        $this->assertTrue($htpasswd->userExists('test_user1'));
        $this->assertFalse($htpasswd->userExists('invalid'));
    }

    /**
     * @test
     */
    public function update_user_method_should_be_used_while_adding_user()
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

    /**
     * @test
     */
    public function dont_update_anything_while_adding_user_if_the_user_already_exists()
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

    /**
     * @test
     * @dataProvider invalidUserNames
     */
    public function validate_username_while_updating($user, $error)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($error);

        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->updateUser($user, '12345768');
    }

    /**
     * @test
     */
    public function update_user_auto_creates_user_if_not_exists()
    {
        $htpasswd = new Htpasswd($this->passFile);
        $returnVal = $htpasswd->updateUser('new_user', '12345768');

        $this->assertTrue($returnVal);
        $content = file($this->passFile);
        $lastLine = end($content);
        $this->assertCount(4, $content);
        $this->assertStringStartsWith('new_user:', $lastLine);
    }

    /**
     * @test
     * @dataProvider validAlgorithms
     */
    public function update_user($encType)
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

    /**
     * @test
     */
    public function invalid_encryption_should_throw_an_exception()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid encryption type');

        $htpasswd = new Htpasswd($this->passFile);
        $returnVal = $htpasswd->updateUser('test_user1', '12345768', 'invalid');
    }

    /**
     * @test
     */
    public function crypt_is_the_default_encryption_type()
    {
        $htpasswd = new Htpasswd($this->passFile);
        $returnVal = $htpasswd->updateUser('test_user1', '87654321');

        $this->assertTrue($returnVal);

        $users = $htpasswd->getUsers();
        $pass = $users['test_user1'];
        $this->assertEncType(Htpasswd::ENCTYPE_CRYPT, $pass, '87654321');
    }

    /**
     * @test
     */
    public function trigger_notice_if_password_is_too_long_for_crypt()
    {
        $this->expectNotice();
        $this->expectNoticeMessage("Only the first 8 characters are taken into account when 'crypt' algorithm is used.");

        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->updateUser('test_user1', '1234567812345678');
    }

    /**
     * @test
     */
    public function delete_user()
    {
        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->deleteUser('test_user2');

        $users = $htpasswd->getUsers();
        $this->assertCount(2, $users);

        $content = file($this->passFile);
        $this->assertCount(2, $content);
        $this->assertNotContains("test_user2:adfasfd\n", $content);
    }

    /**
     * @test
     */
    public function throw_exception_if_non_existent_user_is_tried_to_be_deleted()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User not found');

        $htpasswd = new Htpasswd($this->passFile);
        $htpasswd->deleteUser('asdfasd');
    }

    public function invalidUserNames()
    {
        return [
            ['user:invalid', 'Invalid username. Username cannot contain colon (:) character'],
            [str_repeat('x', 257), 'Usernames cannot be longer than 256 bytes'],
        ];
    }

    public function validAlgorithms()
    {
        return [
            [Htpasswd::ENCTYPE_APR_MD5],
            [Htpasswd::ENCTYPE_CRYPT],
            [Htpasswd::ENCTYPE_SHA1],
        ];
    }

    private function assertEncType($encType, $encryptedPass, $plainPass)
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
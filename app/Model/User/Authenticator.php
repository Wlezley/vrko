<?php

declare(strict_types=1);

namespace App\Model\User;

use Nette;

use Nette\Database\Explorer;
//use Nette\Security\IAuthenticator;
use Nette\Security\Passwords;
use Nette\Security\Identity;
use Nette\Security\SimpleIdentity;
//use Nette\Security\AuthenticationException;


class Authenticator implements Nette\Security\Authenticator
{
	/** @var Nette\Database\Explorer */
	protected $database;

	/** @var Passwords */
	private $passwords;

	public function __construct(Explorer $database, Passwords $passwords)
	{
		$this->database = $database;
		$this->passwords = $passwords;
	}

	/**
	 * @param array $credentials
	 * @return Identity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(string $username, string $password) : Nette\Security\IIdentity
	{
		$row = $this->database->table('user_accounts')->where('username', $username)->fetch();

		if (!($row && $this->passwords->verify($password, $row->password))) {
			throw new Nette\Security\AuthenticationException('Nesprávné přihlašovací údaje');
		}

		$user = $row->toArray();
		unset($user['password']);

		return new Nette\Security\Identity($user['id'], $user['role'], $user);
	}

	/**
	 * @param $username
	 * @param $password
	 * @param string $role
	 */
	public function addUser(string $username, string $password, string $role = "user")
	{
		$this->database->table('user_accounts')->insert([
			'username' => $username,
			'password' => $this->passwords->hash($password),
			'role'	   => $role
		]);
	}
}

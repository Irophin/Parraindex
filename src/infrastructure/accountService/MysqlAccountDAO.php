<?php

namespace App\infrastructure\accountService;

use App\application\login\AccountDAO;
use App\infrastructure\database\DatabaseConnection;
use App\model\account\Account;
use App\model\account\Password;
use App\model\person\Identity;
use App\model\person\PersonBuilder;

/**
 * MySQL implementation of the AccountDAO interface
 */
class MysqlAccountDAO implements AccountDAO
{
    /**
     * @var DatabaseConnection The database connection
     */
    private DatabaseConnection $databaseConnection;


    /**
     * @param DatabaseConnection $databaseConnection The database connection
     */
    public function __construct(DatabaseConnection $databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
    }


    /**
     * @param string $login login of the account
     * @return Password password of the account, em if the account does not exist
     */
    public function getAccountPassword(string $login): Password
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare("SELECT password FROM Account WHERE email = :login LIMIT 1");
        $query->execute(['login' => $login]);
        $result = $query->fetch();
        $password = new Password('');

        if ($result) {
            $password = new Password($result->password);
        }

        $query->closeCursor();
        $connection = null;
        return $password;
    }


    /**
     * @param Account $account The account to create
     * @return void
     */
    public function createAccount(Account $account): void
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare(<<<SQL
                            INSERT INTO Account (email, password, id_person)
                            VALUES (:email, :password, :person)
SQL
        );
        $query->execute([
            'email' => $account->getLogin(),
            'password' => $account->getHashedPassword(),
            'person' => $account->getPersonId()
        ]);

        $query = $connection->prepare(<<<SQL
                            INSERT INTO Privilege (id_account, id_school, privilege_name)
                            VALUES ((SELECT id_account FROM Account WHERE email = :email), 1, 'STUDENT')
SQL
        );
        $query->execute(['email' => $account->getLogin()]);

        $connection = null;
        $query->closeCursor();
    }


    /**
     * @param string $login The login of the account
     * @return bool true if the account exists, false otherwise
     */
    public function existsAccount(string $login): bool
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare("SELECT * FROM Account WHERE email = :email");
        $query->execute(['email' => $login]);
        $result = $query->fetch();

        $connection = null;
        return (bool)$result;
    }


    /**
     * @param Identity $identity The identity of the person
     * @return bool true if the person exists, false otherwise
     */
    public function existsAccountByIdentity(Identity $identity): bool
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare(<<<SQL
                                SELECT *
                                FROM Account
                                    JOIN Person P on P.id_person = Account.id_person
                                WHERE first_name = :first_name
                                  AND last_name = :last_name
SQL
        );
        $query->execute(['first_name' => $identity->getFirstName(), 'last_name' => $identity->getLastName()]);
        $result = $query->fetch();

        $connection = null;
        return (bool)$result;
    }


    /**
     * @param Account $account The temporary account to create
     * @param string $token The token to confirm the account creation
     * @return void
     */
    public function createTemporaryAccount(Account $account, string $token): void
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare(<<<SQL
                                INSERT INTO TemporaryAccount (email, password, id_person, link)
                                VALUES (:email, :password, :person, :link)
SQL
        );
        $query->execute([
            'email' => $account->getLogin(),
            'password' => $account->getHashedPassword(),
            'person' => $account->getPersonId(),
            'link' => $token
        ]);

        $connection = null;
        $query->closeCursor();
    }


    /**
     * @param string $token The token related to the temporary account
     * @return Account The temporary account, with id = -1 if the account does not exist
     */
    public function getTemporaryAccountByToken(string $token): Account
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare("SELECT * FROM TemporaryAccount WHERE link = :token");
        $query->execute(['token' => $token]);

        $account = new Account(-1, '', PersonBuilder::aPerson()->build(), new Password(''));

        if ($result = $query->fetch()) {
            $account = new Account(
                -2,
                $result->email,
                PersonBuilder::aPerson()->withId($result->id_person)->build(),
                new Password($result->password)
            );
        }

        $connection = null;
        $query->closeCursor();
        return $account;
    }


    /**
     * @param Account $account The temporary account to delete
     * @return void
     */
    public function deleteTemporaryAccount(Account $account): void
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare("DELETE FROM TemporaryAccount WHERE id_person = :id");
        $query->execute(['id' => $account->getPersonId()]);

        $connection = null;
        $query->closeCursor();
    }


    /**
     * @param string $login The login of the account
     * @return Account|null The account, null if the account does not exist
     */
    public function getAccountByLogin(string $login): ?Account
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare("SELECT * FROM Account WHERE email = :email LIMIT 1");
        $query->execute(['email' => $login]);
        $account = new Account(-1, '', PersonBuilder::aPerson()->build(), new Password(''));

        if ($result = $query->fetch()) {
            $account = new Account(
                $result->id_account,
                $result->email,
                PersonBuilder::aPerson()->withId($result->id_person)->build(),
                new Password($result->password)
            );
        }

        $query->closeCursor();
        $connection = null;
        return $account;
    }


    /**
     * @param Account $account The account which asked for a password reset
     * @param string $token The token to confirm the password reset
     * @return void
     */
    public function createResetpassword(Account $account, string $token): void
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare(<<<SQL
                                INSERT INTO ResetPassword (id_account, password, link)
                                VALUES (:id, :password, :link)
SQL
        );
        $query->execute([
            'id' => $account->getId(),
            'password' => $account->getHashedPassword(),
            'link' => $token
        ]);

        $connection = null;
        $query->closeCursor();
    }


    /**
     * @param string $token The token related to the password reset
     * @return Account The account, with id = -1 if the account does not exist
     */
    public function getAccountResetPasswordByToken(string $token): Account
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare("SELECT * FROM ResetPassword WHERE link = :token");
        $query->execute(['token' => $token]);

        $account = new Account(-1, '', PersonBuilder::aPerson()->build(), new Password(''));

        if ($result = $query->fetch()) {
            $account = new Account(
                $result->id_account,
                '',
                PersonBuilder::aPerson()->build(),
                new Password($result->password)
            );
        }

        $connection = null;
        $query->closeCursor();
        return $account;
    }


    /**
     * @param Account $account The account which asked for a password reset
     * @return void
     */
    public function editAccountPassword(Account $account): void
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare("UPDATE Account SET password = :password WHERE id_account = :id");
        $query->execute([
            'password' => $account->getHashedPassword(),
            'id' => $account->getId()
        ]);

        $connection = null;
        $query->closeCursor();
    }


    /**
     * @param Account $account The account which asked for a password reset
     * @return void
     */
    public function deleteResetPassword(Account $account): void
    {
        $connection = $this->databaseConnection->getDatabase();

        $query = $connection->prepare("DELETE FROM ResetPassword WHERE id_account = :id");
        $query->execute(['id' => $account->getId()]);

        $connection = null;
        $query->closeCursor();
    }
}

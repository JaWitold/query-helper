<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests;

use Doctrine\Common\Collections\Order;
use Pkly\QueryHelper\QueryHelper;
use Pkly\QueryHelper\Tests\Fixtures\Entity\User;

class QueryHelperTest extends AbstractDoctrineTestCase
{
    public function testListReturnsEmptyCollectionWhenNoEntities(): void
    {
        static::assertCount(0, $this->userHelper()->list());
    }

    public function testListReturnsAllEntities(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        static::assertCount(2, $this->userHelper()->list());
    }

    public function testValueReturnsNullWhenNoMatch(): void
    {
        static::assertNull($this->userHelper()->value());
    }

    public function testValueReturnsSingleEntity(): void
    {
        $user = $this->makeUser();

        $result = $this->userHelper()->value();

        static::assertInstanceOf(User::class, $result);
        static::assertSame($user->getId(), $result->getId());
    }

    public function testValueDoesNotMutateLimit(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        $helper = $this->userHelper()->limit(10);
        $helper->value();

        static::assertCount(2, $helper->list());
    }

    public function testIdReturnsNullWhenNoMatch(): void
    {
        static::assertNull($this->userHelper()->id());
    }

    public function testIdReturnsSingleId(): void
    {
        $user = $this->makeUser();

        static::assertSame($user->getId(), $this->userHelper()->id());
    }

    public function testIdsReturnsAllIds(): void
    {
        $alice = $this->makeUser('Alice', 'alice@example.com');
        $bob = $this->makeUser('Bob', 'bob@example.com');

        $ids = $this->userHelper()->ids();

        static::assertCount(2, $ids);
        static::assertContains($alice->getId(), $ids->toArray());
        static::assertContains($bob->getId(), $ids->toArray());
    }

    public function testCountReturnsZeroWhenEmpty(): void
    {
        static::assertSame(0, $this->userHelper()->count());
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        static::assertSame(2, $this->userHelper()->count());
    }

    public function testExistsReturnsFalseWhenEmpty(): void
    {
        static::assertFalse($this->userHelper()->exists());
    }

    public function testExistsReturnsTrueWhenEntityPresent(): void
    {
        $this->makeUser();

        static::assertTrue($this->userHelper()->exists());
    }

    public function testReferenceReturnsNullWhenNoMatch(): void
    {
        static::assertNull($this->userHelper()->reference());
    }

    public function testReferenceReturnsObject(): void
    {
        $user = $this->makeUser();

        $ref = $this->userHelper()->reference();

        static::assertInstanceOf(User::class, $ref);
        static::assertSame($user->getId(), $ref->getId());
    }

    public function testReferencesReturnsCollection(): void
    {
        $alice = $this->makeUser('Alice', 'alice@example.com');
        $bob = $this->makeUser('Bob', 'bob@example.com');

        $refs = $this->userHelper()->references();

        static::assertCount(2, $refs);
        $refIds = $refs->map(fn (User $u) => $u->getId())->toArray();
        static::assertContains($alice->getId(), $refIds);
        static::assertContains($bob->getId(), $refIds);
    }

    public function testFieldListReturnsScalarArrays(): void
    {
        $user = $this->makeUser('Alice', 'alice@example.com');

        $rows = $this->userHelper()->fieldList(['id', 'name']);

        static::assertCount(1, $rows);
        $row = $rows[0];
        static::assertIsArray($row);
        static::assertSame($user->getId(), $row['id']);
        static::assertSame('Alice', $row['name']);
    }

    public function testFieldsReturnsNullWhenNoMatch(): void
    {
        static::assertNull($this->userHelper()->fields(['id', 'name']));
    }

    public function testFieldsReturnsSingleRow(): void
    {
        $this->makeUser('Alice', 'alice@example.com');

        $row = $this->userHelper()->fields(['name']);

        static::assertIsArray($row);
        static::assertSame('Alice', $row['name']);
    }

    public function testLimitRestrictsResults(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');
        $this->makeUser('Carol', 'carol@example.com');

        static::assertCount(2, $this->userHelper()->limit(2)->list());
    }

    public function testOffsetSkipsRows(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');
        $this->makeUser('Carol', 'carol@example.com');

        $result = $this->userHelper()->orderBy('entity.id')->offset(1)->list();

        static::assertCount(2, $result);
    }

    public function testLimitNullRemovesLimit(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        $helper = $this->userHelper()->limit(1);
        static::assertCount(1, $helper->list());

        $helper->limit(null);
        static::assertCount(2, $helper->list());
    }

    public function testOrderByAscending(): void
    {
        $this->makeUser('Bob', 'bob@example.com');
        $this->makeUser('Alice', 'alice@example.com');

        $names = $this->userHelper()
            ->orderBy('entity.name', Order::Ascending)
            ->fieldList(['name'])
            ->map(fn (array $r) => $r['name'])
            ->toArray();

        static::assertSame(['Alice', 'Bob'], $names);
    }

    public function testOrderByDescending(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        $names = $this->userHelper()
            ->orderBy('entity.name', Order::Descending)
            ->fieldList(['name'])
            ->map(fn (array $r) => $r['name'])
            ->toArray();

        static::assertSame(['Bob', 'Alice'], $names);
    }

    public function testAddOrderByActsAsATieBreakerWithoutReplacingThePrimarySort(): void
    {
        $this->makeUser('Alice', 'alice-a@example.com');
        $this->makeUser('Alice', 'alice-b@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        $emails = $this->userHelper()
            ->orderBy('entity.name', Order::Ascending)
            ->addOrderBy('entity.email', Order::Descending)
            ->fieldList(['email'])
            ->map(fn (array $r) => $r['email'])
            ->toArray();

        static::assertSame([
            'alice-b@example.com',
            'alice-a@example.com',
            'bob@example.com',
        ], $emails);
    }

    public function testListWithWhereClause(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('entity')
            ->from(User::class, 'entity')
            ->where('entity.name = :name')
            ->setParameter('name', 'Alice');

        $result = new QueryHelper($qb)->list(); // @phpstan-ignore-line

        static::assertCount(1, $result);
        $first = $result->first();
        static::assertInstanceOf(User::class, $first);
        static::assertSame('Alice', $first->getName());
    }

    /** @return QueryHelper<User, int> */
    private function userHelper(): QueryHelper
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('entity')
            ->from(User::class, 'entity');

        return new QueryHelper($qb); // @phpstan-ignore-line
    }

    private function makeUser(
        string $name = 'Alice',
        string $email = 'alice@example.com'
    ): User {
        $user = new User($name, $email);
        $this->persist($user);

        return $user;
    }
}

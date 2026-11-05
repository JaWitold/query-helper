<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests;

use Doctrine\Common\Collections\Order;
use Pkly\QueryHelper\QueryHelper;
use Pkly\QueryHelper\Tests\Fixtures\Entity\Post;
use Pkly\QueryHelper\Tests\Fixtures\Entity\User;

class PartialsTest extends AbstractDoctrineTestCase
{
    public function testPartialsLoadOnlySelectedFields(): void
    {
        $this->persist(new User('Alice', 'alice@example.com'), new User('Bob', 'bob@example.com'));
        $this->getEntityManager()->clear();

        $users = $this->userHelper()->orderBy('entity.name', Order::Ascending)->partials(['name']);

        static::assertCount(2, $users);
        static::assertSame(['Alice', 'Bob'], $users->map(fn (User $u) => $u->getName())->toArray());

        $first = $users->first();
        static::assertInstanceOf(User::class, $first);
        static::assertNotNull($first->getId());
        static::assertFalse((new \ReflectionProperty(User::class, 'email'))->isInitialized($first));
    }

    public function testPartialsAlwaysSelectIdentifierAndSkipOtherColumns(): void
    {
        $this->persist(new User('Alice', 'alice@example.com'));
        $this->getEntityManager()->clear();

        $sql = $this->captureLastSql(fn () => $this->userHelper()->partials(['name']));

        static::assertStringContainsString('id', $sql);
        static::assertStringContainsString('name', $sql);
        static::assertStringNotContainsString('email', $sql);
    }

    public function testPartialsAcceptOwningToOneAssociation(): void
    {
        $author = new User('Alice', 'alice@example.com');
        $this->persist($author, new Post('Hello', $author));
        $this->getEntityManager()->clear();

        $qb = $this->getEntityManager()->createQueryBuilder()->select('entity')->from(Post::class, 'entity');
        $post = (new QueryHelper($qb))->partial(['author']); // @phpstan-ignore-line

        static::assertInstanceOf(Post::class, $post);
        static::assertSame($author->getId(), $post->getAuthor()->getId());
        static::assertFalse((new \ReflectionProperty(Post::class, 'title'))->isInitialized($post));
    }

    public function testPartialReturnsNullWhenNoMatch(): void
    {
        static::assertNull($this->userHelper()->partial(['name']));
    }

    public function testPartialDoesNotMutateLimit(): void
    {
        $this->persist(new User('Alice', 'alice@example.com'), new User('Bob', 'bob@example.com'));
        $this->getEntityManager()->clear();

        $helper = $this->userHelper()->limit(10);

        static::assertInstanceOf(User::class, $helper->partial(['name']));
        static::assertCount(2, $helper->partials(['name']));
    }

    /**
     * @return QueryHelper<User, int>
     */
    private function userHelper(): QueryHelper
    {
        $qb = $this->getEntityManager()->createQueryBuilder()->select('entity')->from(User::class, 'entity');

        return new QueryHelper($qb); // @phpstan-ignore-line
    }
}

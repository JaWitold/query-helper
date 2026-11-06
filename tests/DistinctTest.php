<?php

declare(strict_types=1);

namespace Pkly\QueryHelper\Tests;

use Doctrine\ORM\QueryBuilder;
use Pkly\QueryHelper\QueryHelper;
use Pkly\QueryHelper\Tests\Fixtures\Entity\Post;
use Pkly\QueryHelper\Tests\Fixtures\Entity\User;

/**
 * The fixture used here joins one user against two of their posts, so every unfiltered query
 * returns the same user twice — a distinct-aware call must collapse that back down to one.
 */
class DistinctTest extends AbstractDoctrineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = new User('Alice', 'alice@example.com');
        $this->persist($user, new Post('Post 1', $user), new Post('Post 2', $user));
    }

    /**
     * Object hydration already collapses repeated root entities by identity, so the only thing
     * distinct changes for list() is the SQL — see testListWithLimitCountsDistinctRows() for a
     * case where that difference is observable in the results.
     */
    public function testListSelectsDistinctRows(): void
    {
        $plain = $this->helper();
        $distinct = $this->helper()->distinct();

        static::assertStringNotContainsString('DISTINCT', $this->captureLastSql(fn () => $plain->list()));
        static::assertStringContainsString('SELECT DISTINCT', $this->captureLastSql(fn () => $distinct->list()));
    }

    public function testListWithLimitCountsDistinctRows(): void
    {
        $bob = new User('Bob', 'bob@example.com');
        $this->persist($bob, new Post('Post 3', $bob), new Post('Post 4', $bob));

        // Without distinct the two duplicate Alice rows eat the whole limit.
        static::assertCount(1, $this->helper()->orderBy('entity.id')->limit(2)->list());
        static::assertCount(2, $this->helper()->distinct()->orderBy('entity.id')->limit(2)->list());
    }

    public function testIdsDeduplicates(): void
    {
        static::assertCount(2, $this->helper()->ids());
        static::assertCount(1, $this->helper()->distinct()->ids());
    }

    public function testCountDeduplicates(): void
    {
        static::assertSame(2, $this->helper()->count());
        static::assertSame(1, $this->helper()->distinct()->count());
    }

    public function testReferencesDeduplicates(): void
    {
        static::assertCount(2, $this->helper()->references());
        static::assertCount(1, $this->helper()->distinct()->references());
    }

    public function testFieldListDeduplicates(): void
    {
        static::assertCount(2, $this->helper()->fieldList(['id']));
        static::assertCount(1, $this->helper()->distinct()->fieldList(['id']));
    }

    public function testFieldsAppliesDistinct(): void
    {
        $helper = $this->helper()->distinct();

        $sql = $this->captureLastSql(fn () => $helper->fields(['name']));

        static::assertStringContainsString('SELECT DISTINCT', $sql);
    }

    public function testQueryBuilderLevelDistinctIsHonoredByList(): void
    {
        $helper = $this->helper($this->distinctQueryBuilder());

        static::assertStringContainsString('SELECT DISTINCT', $this->captureLastSql(fn () => $helper->list()));
    }

    public function testQueryBuilderLevelDistinctIsHonoredByIds(): void
    {
        static::assertCount(1, $this->helper($this->distinctQueryBuilder())->ids());
    }

    public function testQueryBuilderLevelDistinctIsHonoredByCount(): void
    {
        $helper = $this->helper($this->distinctQueryBuilder());

        $sql = $this->captureLastSql(fn () => static::assertSame(1, $helper->count()));

        static::assertStringContainsString('COUNT(DISTINCT', $sql);
    }

    public function testQueryBuilderLevelDistinctIsHonoredByFieldList(): void
    {
        static::assertCount(1, $this->helper($this->distinctQueryBuilder())->fieldList(['id']));
    }

    public function testQueryBuilderLevelDistinctCannotBeDisabledByTheHelper(): void
    {
        $helper = $this->helper($this->distinctQueryBuilder())->distinct(false);

        static::assertCount(1, $helper->ids());
        static::assertSame(1, $helper->count());
    }

    public function testCountDoesNotSelectDistinctRows(): void
    {
        $helper = $this->helper()->distinct();

        $sql = $this->captureLastSql(fn () => $helper->count());

        static::assertStringContainsString('COUNT(DISTINCT', $sql);
        static::assertStringNotContainsString('SELECT DISTINCT', $sql);
    }

    public function testCountWithQueryBuilderLevelDistinctDoesNotSelectDistinctRows(): void
    {
        $helper = $this->helper($this->distinctQueryBuilder());

        $sql = $this->captureLastSql(fn () => $helper->count());

        static::assertStringNotContainsString('SELECT DISTINCT', $sql);
    }

    public function testCountWithoutDistinctDoesNotCountDistinctIdentifiers(): void
    {
        $helper = $this->helper();

        $sql = $this->captureLastSql(fn () => $helper->count());

        static::assertStringNotContainsString('DISTINCT', $sql);
    }

    public function testDistinctCanBeTurnedBackOff(): void
    {
        $helper = $this->helper()->distinct();

        static::assertSame(1, $helper->count());
        static::assertCount(1, $helper->ids());

        $helper->distinct(false);

        static::assertSame(2, $helper->count());
        static::assertCount(2, $helper->ids());
    }

    public function testDistinctIsNotWrittenBackToTheWrappedQueryBuilder(): void
    {
        $qb = $this->queryBuilder();
        $helper = $this->helper($qb)->distinct();

        $helper->list();
        $helper->ids();
        $helper->count();
        $helper->fieldList(['id']);

        static::assertFalse($qb->getDQLPart('distinct'));
    }

    public function testDistinctListKeepsACustomSelect(): void
    {
        $qb = $this->queryBuilder()->select('entity, post');

        // Deliberately not going through helper(): a multi-select builder hydrates more than TValue.
        $result = new QueryHelper($qb)->distinct()->list()->toArray();

        static::assertCount(1, array_filter($result, fn (mixed $r) => $r instanceof User));
        static::assertCount(2, array_filter($result, fn (mixed $r) => $r instanceof Post));
    }

    /**
     * @return QueryHelper<User, int>
     */
    private function helper(
        QueryBuilder|null $queryBuilder = null
    ): QueryHelper {
        return new QueryHelper($queryBuilder ?? $this->queryBuilder()); // @phpstan-ignore-line
    }

    private function queryBuilder(): QueryBuilder
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('entity')
            ->from(User::class, 'entity')
            ->join(Post::class, 'post', 'WITH', 'post.author = entity');
    }

    private function distinctQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder()->distinct();
    }
}

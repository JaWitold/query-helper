# QueryHelper
Simple utility class for easier use of Doctrine ORM Queries in certain scenarios

[![Packagist Downloads](https://img.shields.io/packagist/dt/pkly/query-helper)](https://packagist.org/packages/pkly/query-helper)

## Installation

Simply run

```
composer require pkly/query-helper
```


## Usage

In your repositories simply return an instance of QueryHelper instead of a full type.

```php
class FooEntityRepository extends \Doctrine\ORM\EntityRepository
{
    /**
    * @return QueryHelper<FooEntity, int> 
    */
    public function findByOrder(
        Order $order
    ): QueryHelper {
        return new QueryHelper(
            $this->createQueryBuilder('entity')
                ->where('entity.relatedOrder = :relaterOrder')
                ->setParameter('relaterOrder', $order)
        );
    }
}
```

Then use it as normal.

```php
// inside of a service, controller, etc.
$helper = $repository->findByOrder($order);

$helper->ids(); // list of ids
$helper->id(); // single id
$helper->value(); // single object
$helper->list(); // list of objects
$helper->reference(); // single reference from Doctrine
$helper->references(); // list of references
$helper->fields(['order']); // single array of fields (one row)
$helper->fieldList(['order']); // list of fields (multiple rows)
$helper->model(OrderSummary::class, ['id', 'IDENTITY(entity.relatedOrder)']); // single DTO via SELECT NEW
$helper->models(OrderSummary::class, ['id', 'IDENTITY(entity.relatedOrder)']); // list of DTOs via SELECT NEW
$helper->modelRow(['order' => Model::of(OrderSummary::class, ['id'])]); // single row of side-by-side DTOs keyed by alias
$helper->modelRows(['order' => Model::of(OrderSummary::class, ['id'])]); // list of rows of side-by-side DTOs
$helper->count(); // simple count
$helper->exists(); // boolean existence check

// more advanced use
// load one object with lock from query
$object = $em->getRepository(FooEntity::class)
    ->findByOrder($order)
    ->lockMode(LockMode::PESSIMISTIC_WRITE)
    ->value(); // returns T|null
```

### DTO hydration

`models()` / `model()` hydrate rows straight into a plain class through DQL `SELECT NEW`, so no
entity is loaded and nothing touches the UnitOfWork. Fields are passed to the constructor in order;
bare names are prefixed with `entity.`, anything containing a dot or function call is used as-is.

```php
final class OrderSummary
{
    public function __construct(
        private readonly int $id,
        private readonly int $orderId,
    ) {
    }
}

$repository->findByOrder($order)->models(OrderSummary::class, ['id', 'IDENTITY(entity.relatedOrder)']);
```

A field may itself be a `Model`, which becomes a nested `NEW` expression passed to the outer constructor:

```php
$repository->findByOrder($order)
    ->getQueryBuilder()->join('entity.customer', 'c');

$repository->findByOrder($order)->models(OrderDetails::class, [
    'id',
    'total',
    Model::of(CustomerSummary::class, ['c.id', 'c.name']),
]);
```

`modelRows()` / `modelRow()` select several models side by side; every row is an array keyed by the
given aliases:

```php
$rows = $helper->modelRows([
    'order' => Model::of(OrderSummary::class, ['id', 'total']),
    'customer' => Model::of(CustomerSummary::class, ['c.id', 'c.name']),
]);

$rows[0]['order'];    // OrderSummary
$rows[0]['customer']; // CustomerSummary
```

### Feature requests?

Sure, hit me up with an issue if you wish.
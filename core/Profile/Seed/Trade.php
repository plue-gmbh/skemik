<?php

declare(strict_types=1);

namespace Profile\Seed;

use Profile\ClosedList;
use Profile\Erasure;
use Profile\FieldDefinition;
use Profile\LocaleMode;
use Profile\Reach;
use Profile\TypeDefinition;

/* The transaction — the price, the sale and the debt. Two of the three
   answer an erasure request with `hold`: a retention obligation outranks the
   request, and a club that purges a member's Invoice has not complied. */
final readonly class Trade
{
  public function __construct(
    private Base $base = new Base(),
    private Fields $f = new Fields(),
    private ValueObjects $vo = new ValueObjects(),
  ) {}

  /** @return list<TypeDefinition> */
  public function types(): array
  {
    return [
      new TypeDefinition('Offer', Erasure::Purge, LocaleMode::Localise, Reach::Public,
        $this->offer(), $this->base->thing()),
      new TypeDefinition('Order', Erasure::Hold, LocaleMode::None, Reach::House,
        $this->order(), $this->base->thing()),
      new TypeDefinition('Invoice', Erasure::Hold, LocaleMode::None, Reach::House,
        $this->invoice(), $this->base->thing()),
    ];
  }

  /** @return list<FieldDefinition> */
  private function offer(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      /* The offer's own two and the two keys that bear its specification: typed
         once, and the document repeats them inside it, since a search engine
         reads a unit price there. */
      $f->number('price', 'Price')->starter()->keyOf('UnitPriceSpecification', 'price'),
      $f->listed('price_currency', 'Currency', ClosedList::Currency)->starter()
        ->keyOf('UnitPriceSpecification', 'priceCurrency'),
      /* One price per offer, so it sorts, filters and ranges: what it is per, for
         how long and with which tax are said beside it, and monthly beside yearly
         is two offers. */
      ...$vo->flatten('UnitPriceSpecification', 'priceSpecification', 'Price', $f),
      $f->date('price_valid_until', 'Price valid until'),
      $f->date('valid_from', 'Valid from'),
      $f->date('valid_through', 'Valid through'),
      /* Authored, and it is the field that proves the column is about who
         writes: a capacity is a count, this is a commercial statement. */
      $f->select('availability', 'Availability',
        ['InStock', 'LimitedAvailability', 'OutOfStock', 'PreOrder', 'SoldOut', 'BackOrder',
          'MadeToOrder', 'InStoreOnly', 'OnlineOnly', 'Discontinued'])->starter(),
      $f->dateTime('availability_starts', 'Available from'),
      $f->dateTime('availability_ends', 'Available until'),
      /* Typed by a shop without a channel, and written by one under a
         conditional write where there is one: the version guards both alike. */
      $f->number('inventory_level', 'Inventory level')
        ->keyOf('QuantitativeValue', 'value', 'inventoryLevel'),
      $f->number('eligible_quantity', 'Eligible quantity')
        ->keyOf('QuantitativeValue', 'value', 'eligibleQuantity'),
      $f->number('eligible_duration', 'Eligible duration')
        ->keyOf('QuantitativeValue', 'value', 'eligibleDuration'),
      $f->text('eligible_duration_unit', 'Eligible duration: unit')
        ->keyOf('QuantitativeValue', 'unitCode', 'eligibleDuration'),
      $f->term('eligible_region', 'Eligible region', 'region')->repeatable(),
      $f->term('ineligible_region', 'Ineligible region', 'region')->repeatable(),
      $f->term('accepted_payment_method', 'Payment methods', 'paymentMethod')->repeatable(),
      $f->select('item_condition', 'Condition',
        ['NewCondition', 'UsedCondition', 'RefurbishedCondition', 'DamagedCondition']),
      $f->number('advance_booking_requirement', 'Advance booking')
        ->keyOf('QuantitativeValue', 'value', 'advanceBookingRequirement'),
      $f->text('advance_booking_requirement_unit', 'Advance booking: unit')
        ->keyOf('QuantitativeValue', 'unitCode', 'advanceBookingRequirement'),
      $f->number('delivery_lead_time', 'Delivery lead time')
        ->keyOf('QuantitativeValue', 'value', 'deliveryLeadTime'),
      $f->text('delivery_lead_time_unit', 'Delivery lead time: unit')
        ->keyOf('QuantitativeValue', 'unitCode', 'deliveryLeadTime'),
      ...$vo->flatten('OfferShippingDetails', 'shippingDetails', 'Shipping', $f),
      ...$vo->flatten('MerchantReturnPolicy', 'hasMerchantReturnPolicy', 'Return policy', $f),
      $f->text('warranty', 'Warranty')->words()
        ->notFlattened('a WarrantyPromise, and the profile declares no such value object'),
      $f->link('checkout_page_url_template', 'Checkout URL template'),
      $f->ref('item_offered', 'What is offered', 'Product', 'Service', 'Event', 'Course',
        'SoftwareApplication', 'MemberProgram')->required(),
      $f->ref('seller', 'Seller', 'Organization')->starter(),
      $f->refs('add_on', 'Add-ons', 'Offer'),
      $f->objects('additional_property', 'Additional property', 'PropertyValue')->words(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function order(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      /* Unique per seller, never globally — and (seller, orderNumber) is the
         idempotency key a retrying channel needs. */
      $f->text('order_number', 'Order number')->starter()->unique('seller'),
      $f->select('order_status', 'Status', ['OrderPaymentDue', 'OrderProcessing',
        'OrderInTransit', 'OrderDelivered', 'OrderCancelled', 'OrderReturned'])->starter(),
      $f->dateTime('order_date', 'Ordered')->starter(),
      /* Offered, not ‡: the invoice's due date is the binding one, and a house
         types it once. */
      $f->date('payment_due_date', 'Payment due'),
      $f->text('confirmation_number', 'Confirmation number'),
      $f->objects('ordered_item', 'Items', 'OrderItem')->required(),
      $f->refs('accepted_offer', 'Accepted offer', 'Offer'),
      $f->number('discount', 'Discount'),
      $f->text('discount_code', 'Discount code'),
      $f->listed('discount_currency', 'Discount currency', ClosedList::Currency),
      $f->boolean('is_gift', 'Gift'),
      $f->term('payment_method', 'Payment method', 'paymentMethod'),
      $f->text('payment_method_id', 'Payment method ID'),
      $f->link('payment_url', 'Payment URL'),
      ...$vo->flatten('PostalAddress', 'billingAddress', 'Billing address', $f),
      $f->ref('customer', 'Customer', 'Person', 'Organization')->required(),
      /* On the first screen because the order number is unique under it: an order
         naming no seller has no scope, and its number is held by nothing. */
      $f->ref('seller', 'Seller', 'Organization')->starter(),
      $f->ref('broker', 'Broker', 'Organization'),
      $f->text('order_delivery', 'Delivery'),
    ];
  }

  /** @return list<FieldDefinition> */
  private function invoice(): array
  {
    $f = $this->f;

    return [
      /* Unique per issuer, and that is the whole of what Skemik promises: Paragraph 14
         UStG also wants the number to run in sequence, which is the house's
         obligation and not ours to fake. */
      /* schema.org's Invoice has no number of its own, and `confirmationNumber`
         confirms a payment: the number is the invoice's one identifier. Not
         required, since a draft is numbered when it is issued. */
      $f->text('identifier', 'Invoice number')->starter()->unique('provider'),
      $f->text('account_id', 'Account'),
      /* Authored, never derived: Invoice has nothing to sum over, and a binding
         total is the business's to state. */
      $f->number('total_payment_due', 'Amount due')->required()
        ->keyOf('MonetaryAmount', 'value', 'totalPaymentDue'),
      /* One currency per invoice, carried by both amounts. It is
         MonetaryAmount's own key, on the type because the value object is
         resolved into fields, so Claims asks MonetaryAmount about it. */
      $f->listed('currency', 'Currency', ClosedList::Currency)->starter()
        ->keyOf('MonetaryAmount', 'currency'),
      $f->number('minimum_payment_due', 'Minimum due')
        ->keyOf('MonetaryAmount', 'value', 'minimumPaymentDue'),
      $f->select('payment_status', 'Payment status', ['PaymentDue', 'PaymentComplete',
        'PaymentPastDue', 'PaymentAutomaticallyApplied', 'PaymentDeclined'])->starter(),
      $f->date('payment_due_date', 'Due')->starter(),
      $f->date('scheduled_payment_date', 'Scheduled payment'),
      $f->duration('billing_period', 'Billing period'),
      $f->term('payment_method', 'Payment method', 'paymentMethod'),
      $f->text('payment_method_id', 'Payment method ID'),
      $f->term('category', 'Category'),
      $f->ref('customer', 'Customer', 'Person', 'Organization')->required(),
      /* On the first screen for the reason an order's `seller` is: the number is
         unique under it. */
      $f->ref('provider', 'Issued by', 'Organization')->starter(),
      $f->ref('broker', 'Broker', 'Organization'),
      $f->refs('references_order', 'Orders', 'Order')->starter(),
    ];
  }
}

BlockTrail PHP SDK Changelog
============================

v1.3.1
------
 - add `Wallet::FEE_STRATEGY_LOW_PRIORITY` which should give a 75% chance to get into the next 3 blocks.

v1.3.0
------
 - Floating Fees

### Floating Fees
To deal with stresstests etc. there's now a `feePerKB` method to get the optimal fee and the `$wallet->pay` has a `$feeStrategy` argument.  
When `$feeStrategy` is `Wallet::FEE_STRATEGY_OPTIMAL` (default) it will use the (by the API calculated) optimal fee per KB.  
When `$feeStrategy` is `Wallet::FEE_STRATEGY_BASE_FEE` it will use the BASE_FEE of 0.0001 BTC per KB and use the old way of rounding the transaction size to 1 KB.

For using the `TransactionBuilder` use the `$txBuilder->setFeeStrategy()` if you don't want the default `Wallet::FEE_STRATEGY_OPTIMAL`.

Optimal fee is calculated by taking all transactions from the last 30 blocks and calculating what the lowest possible fee is 
that still gives more than 75% chance to end up in the next block.

v1.2.17
-------
 - Add support to `TransactionBuilder` for OP_RETURN, see `examples/op_return_payment_api_example.php`

v1.2.16
-------
 - Improved fee estimation

v1.2.15
-------
 - Improved fee estimation

v1.2.14
-------
 - add `readOnly => true` option to initWallet to use a wallet without requiring a password
 - add `TransactionBuilder` class to construct custom transactions
 - add `forceFee` to `pay`
 - allow different ways of specifying outputs for `wallet->pay`

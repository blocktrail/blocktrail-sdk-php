BlockTrail PHP SDK Changelog
============================

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

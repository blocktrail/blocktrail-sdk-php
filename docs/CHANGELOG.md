BlockTrail PHP SDK Changelog
============================

v1.4.0
------
 - New [Default] Wallet Version 2
 - `BackupGenerator` now supports `$extra` to be printed on document for extra notes
 - 2FA support
 - Floating Fees

### DON'T FORGET TO `composer update`

### Upgrade / BC breaks
 - `createNewWallet` now returns `list($wallet, $backupInfo)` to be able to support both v1 and v2
 - `new BackupGenerator()` now takes `$identifier, $backupInfo, $extra`
 - No longer support `BackupGenerator::generateImage`
 - `WalletSweeper` has changed to remove some abstraction, also added insight-api implementation
 - Defaults to using `Wallet::FEE_STRATEGY_OPTIMAL` instead of `Wallet::FEE_STRATEGY_BASE_FEE`!

### New [Default] Wallet Version 2
Instead of using `BIP39`, wallet seeds will now be stored encrypted - to allow for password changes

Wallet Creation:  
```
primarySeed = random()
secret = random()
primaryMnemonic = BIP39.entropyToMnemonic(AES.encrypt(primarySeed, secret))
secretMnemonic = BIP39.entropyToMnemonic(AES.encrypt(secret, password))
```

Wallet Init:  
```
secret = BIP39.entropyToMnemonic(AES.decrypt(secretMnemonic, password))
primarySeed = BIP39.entropyToMnemonic(AES.decrypt(primaryMnemonic, secret))
```

See `docs/KEYS.md` for more info
   
Old Wallets that are v1 will remain so and will continue working.

Wallets created through the Blocktrail website will have an option to choose which version to create (default v2), 
as grace period the first few days after the SDK release this will default to v1 still.

### Floating Fees
To deal with stresstests etc. there's now a `$feePerKB` method to get the optimal fee and the `$wallet->pay` has a `$feeStrategy` argument.  
When `$feeStrategy` is `Wallet::FEE_STRATEGY_OPTIMAL` (default) it will use the (by the API calculated) optimal fee per KB.  
When `$feeStrategy` is `Wallet::FEE_STRATEGY_BASE_FEE` it will use the BASE_FEE of 0.0001 BTC per KB and use the old way of rounding the transaction size to 1 KB.

For using the `TransactionBuilder` use the `$txBuilder->setFeeStrategy()` if you don't want the default `Wallet::FEE_STRATEGY_OPTIMAL`.

Optimal fee is calculated by taking all transactions from the last 30 blocks and calculating what the lowest possible fee is 
that still gives more than 80% chance to end up in the next block.

v1.3.12
-------
 - add batch support for fetching multiple transactions.
 - use .notify on the pay promise for progress.
 - allow for bypassing of local derivation of new address (used to verify API response)

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

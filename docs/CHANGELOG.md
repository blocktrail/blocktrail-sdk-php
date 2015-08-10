BlockTrail PHP SDK Changelog
============================

v1.4.0
------
 - New [Default] Wallet Version 2
 - `BackupGenerator` now supports `$extra` to be printed on document for extra notes
 - No longer support `BackupGenerator::generateImage`

### Upgrade / BC breaks
 - `createNewWallet` now returns `list($wallet, $backupInfo)` to be able to support both v1 and v2
 - `new BackupGenerator()` now takes `$identifier, $backupInfo, $extra`
 - No longer support `BackupGenerator::generateImage`
 - `WalletSweeper` has changed to remove some abstraction, also added insight-api implementation

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

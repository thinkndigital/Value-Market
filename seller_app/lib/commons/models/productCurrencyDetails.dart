class ProductCurrencyDetails {
  final String? currencyCode;
  final String? symbol;
  final String? exchangeRate;
  final String? amount;

  ProductCurrencyDetails({
    this.currencyCode,
    this.symbol,
    this.exchangeRate,
    this.amount,
  });

  ProductCurrencyDetails copyWith({
    String? currencyCode,
    String? symbol,
    String? exchangeRate,
    String? amount,
  }) {
    return ProductCurrencyDetails(
      currencyCode: currencyCode ?? this.currencyCode,
      symbol: symbol ?? this.symbol,
      exchangeRate: exchangeRate ?? this.exchangeRate,
      amount: amount ?? this.amount,
    );
  }

  ProductCurrencyDetails.fromJson(Map<String, dynamic> json)
    : currencyCode = json['currency_code'] as String?,
      symbol = json['symbol'] as String?,
      exchangeRate = json['exchange_rate'] as String?,
      amount = json['amount'] as String?;

  Map<String, dynamic> toJson() => {
    'currency_code' : currencyCode,
    'symbol' : symbol,
    'exchange_rate' : exchangeRate,
    'amount' : amount
  };
}
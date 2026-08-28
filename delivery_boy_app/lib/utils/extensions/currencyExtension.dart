extension CurrencyExtension on String {
String withOrderSymbol({String symbol = '#'}) {
return '$symbol$this';
}
}

import 'dart:convert';

/// EncodingExtensions
extension EncodingExtensions on String {
  String get toBase64 {
    return base64.encode(toUtf8);
  }

  List<int> get toUtf8 {
    return utf8.encode(this);
  }
}

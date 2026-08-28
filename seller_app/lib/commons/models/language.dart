class Language {
  final int? id;
  final String? language;
  final String? nativeLanguage;
  final String? code;
  final int? isRtl;

  Language({
    this.id,
    this.language,
    this.nativeLanguage,
    this.code,
    this.isRtl,
  });

  Language copyWith({
    int? id,
    String? language,
    String? nativeLanguage,
    String? code,
    int? isRtl,
  }) {
    return Language(
      id: id ?? this.id,
      language: language ?? this.language,
      nativeLanguage: nativeLanguage ?? this.nativeLanguage,
      code: code ?? this.code,
      isRtl: isRtl ?? this.isRtl,
    );
  }

  Language.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int?,
        language = json['language'] as String?,
        nativeLanguage = json['native_language'] as String?,
        code = json['code'] as String?,
        isRtl = json['is_rtl'] as int?;

  Map<String, dynamic> toJson() => {
        'id': id,
        'language': language,
        'native_language': nativeLanguage,
        'code': code,
        'is_rtl': isRtl
      };

  bool isThisRTL() => (isRtl == 1);
}

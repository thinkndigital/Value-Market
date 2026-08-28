import 'package:flutter/material.dart';

class DottedLineRectPainter extends CustomPainter {
  final Color color;
  final double strokeWidth;
  final double radius;
  final double dashWidth;
  final double dashSpace;

  DottedLineRectPainter({
    required this.color,
    required this.strokeWidth,
    required this.radius,
    required this.dashWidth,
    required this.dashSpace,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = strokeWidth
      ..style = PaintingStyle.stroke;

    final path = Path();
    path.addRRect(RRect.fromRectAndRadius(
      Rect.fromLTWH(0, 0, size.width, size.height),
      Radius.circular(radius),
    ));

    final pathMetrics = path.computeMetrics().toList();
    for (var metric in pathMetrics) {
      var length = 0.0;
      while (length < metric.length) {
        final dashPath = metric.extractPath(length, length + dashWidth);
        canvas.drawPath(dashPath, paint);
        length += dashWidth + dashSpace;
      }
    }
  }

  @override
  bool shouldRepaint(covariant DottedLineRectPainter oldDelegate) {
    return oldDelegate.color != color ||
        oldDelegate.strokeWidth != strokeWidth ||
        oldDelegate.radius != radius ||
        oldDelegate.dashWidth != dashWidth ||
        oldDelegate.dashSpace != dashSpace;
  }
}

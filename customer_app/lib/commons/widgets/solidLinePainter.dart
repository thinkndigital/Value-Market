import 'package:flutter/material.dart';

class SolidLinePainter extends CustomPainter {
  final Color color;

  SolidLinePainter({required this.color});
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color // Set the color of the line
      ..strokeWidth = 2; // Set the width of the line

    // Draw a vertical line from top to bottom
    canvas.drawLine(
        Offset(size.width / 2, 0), Offset(size.width / 2, size.height), paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) {
    return false;
  }
}

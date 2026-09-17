"""Add a synthetic handwritten stroke inside the customer box for integration tests."""

import io
import sys

from pypdf import PdfReader, PdfWriter
from reportlab.pdfgen import canvas
from PIL import Image
import pypdfium2 as pdfium


reader = PdfReader(sys.argv[1])
writer = PdfWriter()
for page in reader.pages:
    writer.add_page(page)

layer = io.BytesIO()
pen = canvas.Canvas(layer, pagesize=(595, 842))
pen.setLineWidth(2.2)
path = pen.beginPath()
path.moveTo(90, 48)
path.curveTo(116, 70, 132, 36, 150, 58)
path.curveTo(173, 76, 190, 42, 228, 61)
if len(sys.argv) < 4 or sys.argv[3] != "responsible":
    pen.drawPath(path)
pen.line(370, 48, 475, 62)
if len(sys.argv) > 3 and sys.argv[3] == "alter":
    pen.rect(80, 400, 120, 15, fill=1)
pen.save()
layer.seek(0)
writer.pages[-1].merge_page(PdfReader(layer).pages[0])
with open(sys.argv[2], "wb") as output:
    writer.write(output)

if len(sys.argv) > 3 and sys.argv[3] in ("scan", "responsible"):
    document = pdfium.PdfDocument(sys.argv[2])
    images = [page.render(scale=1.5).to_pil().convert("RGB") for page in document]
    images[0].save(sys.argv[2], "PDF", resolution=108, save_all=True, append_images=images[1:])

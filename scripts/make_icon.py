import os
from PIL import Image

try:
    # We will use USGQG-LOGO-NUEVO-COLORES.jpg or LOGO-USFQG-2025.png
    input_path = "html/img/USGQG-LOGO-NUEVO-COLORES.jpg"
    if not os.path.exists(input_path):
        input_path = "html/img/LOGO-USFQG-2025.png"

    output_path = "html/img/apple-touch-icon.png"
    size = 180
    padding = 20
    
    with Image.open(input_path) as img:
        img = img.convert("RGBA")
        
        # Calculate aspect ratio
        aspect = img.width / img.height
        
        # We want the logo to fit within (size - 2*padding)
        target_w = size - 2 * padding
        target_h = size - 2 * padding
        
        if aspect > 1:
            # wider
            new_w = target_w
            new_h = int(target_w / aspect)
        else:
            # taller
            new_h = target_h
            new_w = int(target_h * aspect)
            
        img = img.resize((new_w, new_h), Image.Resampling.LANCZOS)
        
        # Create a white background square
        background = Image.new('RGB', (size, size), (255, 255, 255))
        
        # Paste centered
        offset_x = (size - new_w) // 2
        offset_y = (size - new_h) // 2
        
        # If the input was png with transparency, use it as mask
        if 'A' in img.getbands():
            background.paste(img, (offset_x, offset_y), img)
        else:
            background.paste(img, (offset_x, offset_y))
            
        background.save(output_path, "PNG")
        print(f"Created {output_path} successfully.")

except Exception as e:
    print(f"Failed to create icon: {e}")

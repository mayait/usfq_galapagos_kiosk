import os
import sys

try:
    from PIL import Image, ExifTags
except ImportError:
    print("Pillow not installed. Please try installing it via `pip3 install Pillow`")
    sys.exit(1)

# Target directory with the photos
directory_path = "html/img/photos/Fotos estudiantes para campaña de admisiones 2025 - 2026"
max_size = 1920
quality = 85

if not os.path.exists(directory_path):
    print(f"Directory {directory_path} not found.")
    sys.exit(1)

# Gather all valid image files
files = [f for f in os.listdir(directory_path) if os.path.isfile(os.path.join(directory_path, f))]
images = [f for f in files if f.lower().endswith(('.jpg', '.jpeg', '.png', '.heic'))]

count = 1
for img_name in sorted(images):
    original_path = os.path.join(directory_path, img_name)
    new_name = f"{count}.webp"
    webp_path = os.path.join(directory_path, new_name)
    
    try:
        with Image.open(original_path) as img:
            # Handle orientation to prevent sideways images on resize
            try:
                for orientation in ExifTags.TAGS.keys():
                    if ExifTags.TAGS[orientation]=='Orientation':
                        break
                exif = img._getexif()
                if exif is not None:
                    orientation_val = exif.get(orientation, 1)
                    if orientation_val == 3:
                        img = img.rotate(180, expand=True)
                    elif orientation_val == 6:
                        img = img.rotate(270, expand=True)
                    elif orientation_val == 8:
                        img = img.rotate(90, expand=True)
            except Exception:
                pass
                
            # Resize image maintaining aspect ratio
            img.thumbnail((max_size, max_size), Image.Resampling.LANCZOS)
            
            # Convert to RGB to ensure webp compatibility if there's no alpha
            if img.mode not in ('RGB', 'RGBA'):
                img = img.convert('RGB')
                
            # Save as optimized webp
            img.save(webp_path, "webp", quality=quality)
            print(f"Optimized [{img_name}] -> {new_name}")
            
        # Delete original image to save space
        os.remove(original_path)
        count += 1
    except Exception as e:
        print(f"Failed to process {img_name}: {e}")

print("Image optimization complete.")

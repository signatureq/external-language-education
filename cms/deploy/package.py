"""Build a fresh production archive; never include local accounts, DBs, .env or logs."""
from pathlib import Path
import zipfile, argparse
root=Path(__file__).resolve().parents[2]
cms=root/'cms'
parser=argparse.ArgumentParser();parser.add_argument('output',type=Path);args=parser.parse_args()
private_dirs=['app','bootstrap','config','routes','resources','vendor']
public_dirs=['js','css','fonts']
with zipfile.ZipFile(args.output,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as archive:
    for folder in private_dirs:
        for path in (cms/folder).rglob('*'):
            if path.is_file() and not (folder=='bootstrap' and path.parent.name=='cache'):
                archive.write(path,'ele-cms/'+str(path.relative_to(cms)))
    for folder in ['database/migrations','database/seeders']:
        for path in (cms/folder).rglob('*.php'):archive.write(path,'ele-cms/'+str(path.relative_to(cms)))
    for file in ['artisan','composer.json','composer.lock','.env.example']:
        archive.write(cms/file,'ele-cms/'+file)
    for directory in ['bootstrap/cache','storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs']:
        archive.writestr('ele-cms/'+directory+'/.gitignore','*\n!.gitignore\n')
    for folder in public_dirs:
        for path in (cms/'public'/folder).rglob('*'):
            if path.is_file():archive.write(path,'public_html/'+str(path.relative_to(cms/'public')))
    archive.write(cms/'public/.htaccess','public_html/.htaccess')
    archive.write(cms/'deploy/index.php','public_html/index.php')
    archive.write(cms/'deploy/uploads.htaccess','public_html/uploads/.htaccess')
    archive.write(root/'app.js','public_html/app.js')
    archive.write(cms/'deploy/install.php','ele-cms/install.php')
    archive.write(cms/'deploy/connect-domains.php','ele-cms/connect-domains.php')
print(f'{args.output}: {args.output.stat().st_size/1024/1024:.1f} MB')

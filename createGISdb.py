''' 
国土地理院位置参照情報からのDB作成ツール
容量削減のため、同一緯度経度の情報は一体化
'''
from pathlib import Path
import pandas as pd
import sqlite3

dir = Path(r'C:\Users\coo\Desktop\k')#街区レベルのCSV
sdir = Path(r'C:\Users\coo\Desktop\s')#大字レベルのCSV
db_path = r'C:\Users\coo\Desktop\gis.db'

if __name__ == '__main__':
    #まず大字レベル
    sfiles = sdir.glob('*.csv')
    sdf_concat = pd.DataFrame()
    for file in sfiles:
        with open(file) as f:
            df = pd.read_csv(f,
                encoding='shift_jis',   
                usecols=['都道府県名','市区町村名','大字町丁目名','緯度','経度'],
                dtype={'都道府県名': str,'市区町村名': str,'大字町丁目名':str,'緯度':float,'経度':float}
                #dtype={'都道府県名': str,'市区町村名': str,'大字町丁目名': str,'緯度':str,'経度':str}
            )
            """
            #小数から整数へ変換、サイズが小さい、クエリ時にちょっと速い
            df['緯度'] = df['緯度'].str.replace('.','',regex=False)
            df['緯度'] = df['緯度'].str.ljust(8,'0').astype(int)
            df['経度'] = df['経度'].str.replace('.','',regex=False)
            df['経度'] = df['経度'].str.ljust(9,'0').astype(int)
            """
            df.fillna("",inplace=True)#nanだとgroupbyでバグ
            #各都道府県ごとのファイルを一つにまとめる
            sdf_concat = pd.concat([sdf_concat,df])
    sdf_concat = sdf_concat.rename(columns={
        '緯度': 'Latitude',
        '経度': 'Longitude',
        '都道府県名': 'Prefecture',
        '市区町村名': 'City',
        '大字町丁目名': 'District',
        })

    #指定フォルダ内のCSVファイルが処理対処
    files = dir.glob('*.csv')
    df_concat = pd.DataFrame()
    for file in files:
        with open(file) as f:
            #必要項目のみ読み取り
            df = pd.read_csv(f,
                encoding='shift_jis',   
                usecols=['都道府県名','市区町村名','大字・丁目名','小字・通称名','街区符号・地番','緯度','経度'],
                dtype={'都道府県名': str,'市区町村名': str,'大字・丁目名': str,'小字・通称名': str,'緯度':float,'経度':float, '街区符号・地番': str}
                #dtype={'都道府県名': str,'市区町村名': str,'大字・丁目名': str,'小字・通称名': str,'緯度':str,'経度':str, '街区符号・地番': str}
            )
            """
            #小数から整数へ変換、サイズが小さい、クエリ時にちょっと速い
            df['緯度'] = df['緯度'].str.replace('.','',regex=False)
            df['緯度'] = df['緯度'].str.ljust(8,'0').astype(int)
            df['経度'] = df['経度'].str.replace('.','',regex=False)
            df['経度'] = df['経度'].str.ljust(9,'0').astype(int)
            """
            #sqlite用に並び替え
            df = df.loc[:, ['都道府県名','市区町村名','大字・丁目名','小字・通称名','街区符号・地番','緯度','経度']]
            df.fillna("",inplace=True)#nanだとgroupbyでバグ
            #地番以外が同一のものをまとめる（容量削減のため）
            grp = df.groupby(['都道府県名','市区町村名','大字・丁目名','小字・通称名','緯度','経度'])
            print(grp.size())
            num = grp.agg({'街区符号・地番': lambda x: '-'.join(x)}).reset_index()
            #df.at[index, '街区符号・地番'] = row['age']
            #各都道府県ごとのファイルを一つにまとめる
            #num.to_csv(f.name.replace(".csv","_.csv"), index = False)
            df_concat = pd.concat([df_concat,num])
    
    #不要な文字を削る
    df_concat = df_concat.replace('（大字なし）', '').replace('-.*-', '-', regex=True)
    #クエリ用にカラム名変更
    df_concat = df_concat.rename(columns={
        '緯度': 'Latitude',
        '経度': 'Longitude',
        '都道府県名': 'Prefecture',
        '市区町村名': 'City',
        '大字・丁目名': 'District',
        '小字・通称名': 'Street',
        '街区符号・地番': 'Numbers'
        })
    #df_concat.to_csv(r'C:\Users\coo\Desktop\GEOList.csv', index = False)
    with sqlite3.connect(db_path) as conn:
        sdf_concat.to_sql('simple', con=conn,index=False)
        #高速化のため緯度経度にindex
        conn.execute('CREATE INDEX sidx ON simple(Latitude ASC,Longitude ASC)')

        df_concat.to_sql('detail', con=conn,index=False)
        #高速化のため緯度経度にindex
        conn.execute('CREATE INDEX didx ON detail(Latitude ASC,Longitude ASC)')



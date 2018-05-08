import java.io.IOException;
import java.util.StringTokenizer;
import org.apache.hadoop.conf.Configuration;
import org.apache.hadoop.fs.Path;
import org.apache.hadoop.io.IntWritable;
import org.apache.hadoop.io.Text;
import org.apache.hadoop.mapreduce.Job;
import org.apache.hadoop.mapreduce.Mapper;
import org.apache.hadoop.mapreduce.Reducer;
import org.apache.hadoop.mapreduce.lib.input.FileInputFormat;
import org.apache.hadoop.mapreduce.lib.output.FileOutputFormat;
import java.util.*;

public class InvertedIndex {

  public static class TokenizerMapper
       extends Mapper<Object, Text, Text, Text>{

    private Text word = new Text();
  	private Text documentID = new Text();

    public void map(Object key, Text value, Context context
                    ) throws IOException, InterruptedException {
      String input[] = value.toString().split("\\s+",2);
  	  String words[] = input[1].split("\\s+");
  	  documentID.set(input[0]);
  	  for(String each:words){
          word.set(each);
          context.write(word, documentID);
        }
    }
  }

  public static class IntSumReducer
       extends Reducer<Text,Text,Text,Text> {
	
    private Text result = new Text();
    public void reduce(Text key, Iterable<Text> values,
                       Context context
                       ) throws IOException, InterruptedException {
      HashMap<String,Integer> dict = new HashMap<String,Integer>();
      String doc;
      String outputString = "";

  	  for(Text one:values){
  		  doc = one.toString();
  		  if(dict.containsKey(doc))
  			  dict.put(doc,dict.get(doc)+1);
  		  else 
  			  dict.put(doc,1);
  	  }
  	  
  	  for(String s: dict.keySet()){
  		  outputString += " "+s+":"+dict.get(s);
  	  }
      
  	  result.set(outputString);
  	  context.write(key,result);
  			  
    }
  }

  public static void main(String[] args) throws Exception {
    Configuration conf = new Configuration();
    Job job = Job.getInstance(conf, "word count");
    job.setJarByClass(InvertedIndex.class);
    job.setMapperClass(TokenizerMapper.class);
    job.setReducerClass(IntSumReducer.class);
    job.setOutputKeyClass(Text.class);
    job.setOutputValueClass(Text.class);
    FileInputFormat.addInputPath(job, new Path(args[0]));
    FileOutputFormat.setOutputPath(job, new Path(args[1]));
    System.exit(job.waitForCompletion(true) ? 0 : 1);
  }
}